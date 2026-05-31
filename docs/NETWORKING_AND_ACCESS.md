# GQS Networking & Access, Findings and Options (for a future deep-dive chat)

**Last updated:** 31-MAY-2026
**Status:** Investigation complete; no server changes made yet. This documents what is TRUE,
what was ruled out, and the concrete options to make gowning reachable from the office network.
**Pair with:** `GQS_MASTER_PLAN_v4.md`, `GQS_OPEN_ISSUES.md`, `DEPLOY.md`.

---

## TL;DR

- The gowning app, Apache, the TLS cert, the Vite manifest, and routing all WORK. `curl` from the
  server returns full gowning HTML. The home user reaches the site fine. There is nothing left to
  fix in the app for it to serve.
- "Turn off HTTPS" was the wrong lever. The protocol was never the problem.
- The real blocker for the on-site user is a NETWORK/FIREWALL issue on the office segment, plus a
  cert name mismatch when browsing by IP. Both are infrastructure, not app, not code.
- A separate, now-FIXED problem: a stale Vite-manifest 500 and a file-ownership (EACCES) issue.
  Cleared with `optimize:clear` + `filament:assets` run as `www-data`, and a `chown`/`chmod`.

---

## The environment (verified facts)

Server `ip-172-17-237-191` (private IP `172.17.237.191`). Apache 2.4.58 (Ubuntu). Deploy user
`matc-web`; web/PHP user `www-data`.

`nslookup matcastellas.com` on the server resolves to **172.17.237.191** (the private IP). This is
split-horizon DNS: inside the network the public hostname points at the internal address.

`sudo apachectl -S` virtual hosts:
- `*:8080` -> `matcastellas.com` (`000-default.conf`). **HTTPS** (`SSLEngine on`, Let's Encrypt cert
  for `matcastellas.com`). Hosts MANY apps by path: MTS (`/`-> `/MTS/`), **gowning** (`Alias /gowning
  -> /var/www/html/gowning/public`), matcproject, draw, excalidraw, GLPI (`/service`), plus proxies
  to SciNote and OpenCloning, and a server-wide `RewriteRule ^/livewire/(.*)$ /MATCit/livewire/$1 [P,L]`.
- `*:443` -> `matcastellas.com` (`e3server-ssl.conf`). Separate HTTPS vhost (same cert).
- `*:8090` -> `matcastellas.com` (`matc-ssl-8090.conf`). **HTTPS reverse-proxy**: `ProxyPass "/"
  "http://localhost:8090/"` with `X-Forwarded-*` headers. Intended front door for the app on
  localhost:8090.
- `*:8092` -> InvenTree (`inventree-ssl.conf` / `inventree.conf`), name-based, HTTPS.
- `*:8094` -> elabftw (`localhost`).

`sudo ss -ltnp` listeners (the decisive evidence):
- `0.0.0.0:8090` owned by **`ruby`** (pids 29227, 29051). **OpenProject (Ruby) is bound directly to
  ALL interfaces on 8090, plain HTTP, no TLS.** This is why hitting `:8090` reaches Ruby, NOT Apache.
- `*:443` and `*:8080` owned by `apache2` (many workers). So Apache IS listening on 8080 and 443.

Browser/curl behaviour observed:
- `http://172.17.237.191:8080/gowning/` -> Apache 400: "You're speaking plain HTTP to an SSL-enabled
  server port." (Correct: 8080 is HTTPS-only.)
- `https://matcastellas.com:8080/gowning/` -> **TIMES OUT for the on-site user**, but **WORKS for a
  user at home**.
- `http://172.17.237.191:8090/` and `http://matcastellas.com:8090/` -> **WORK for the on-site user**,
  no cert warning (because it is plain HTTP straight to Ruby; no certificate is involved).
- `curl` from the SERVER to `https://matcastellas.com:8080/gowning/` and `.../...:8090/` both return
  HTML. (Server talking to itself over the private IP; always works. Does NOT test the office path.)

`.env` (relevant keys), confirmed correct:
```
APP_URL=https://matcastellas.com:8080/gowning
SESSION_SECURE_COOKIE=true
APP_KEY=base64:... (set)
```

---

## Why one user can reach the site and another cannot (root cause)

Same server, same port 8080, home user succeeds and on-site user times out. That is NOT a server
problem; it is the network path between the on-site workstation and port 8080. Two compounding causes:

1. **Office firewall/proxy filters the port.** Corporate segments commonly allow only 80/443 and
   block "odd" ports. The on-site user's traffic to 8080 is dropped (times out); the home user's
   network has no such rule. NOTE: IT says 8080 is "allowed," yet it still times out for the on-site
   user, so either the rule is keyed to the public path (and the user resolves to the PRIVATE IP via
   split-horizon DNS), or 8080 is not actually permitted to that segment despite approval. Unresolved;
   needs a workstation-side test (below) and an IT confirmation.

2. **Cert name mismatch when browsing by IP.** The cert is for `matcastellas.com`. Browsing
   `https://172.17.237.191:8080` throws a "not secure" warning (the connection is allowed; the
   browser complains). Plain HTTP has no cert, so the IP works without warning, which is exactly why
   OpenProject-on-8090 "just works" by IP.

**The difference the user is seeing is HTTP-without-a-cert (8090, Ruby) vs HTTPS-with-a-cert (8080,
Apache) - NOT "IP vs domain." Plain HTTP is indifferent to hostname; TLS is not.**

### The one missing test (must be run FROM THE WORKSTATION, not the server)
```
curl -kv https://matcastellas.com:8080/gowning/ --max-time 10
curl -kv https://matcastellas.com:8090/ --max-time 10
```
- 8080 hangs at "Trying 172.17.237.191:8080..." then times out -> port 8080 is filtered for that
  segment. Hand the timeout to Astellas IT.
- 8090 connects (cert warning OK) -> 8090 is genuinely reachable for that user (consistent with the
  browser reaching OpenProject there).
- "Could not resolve host" -> a DNS issue instead.

---

## Allowed ports (hard constraint from IT)

IT will provide only **8080, 8090, 8092**. (8094 was requested, believed NOT open.) So any
office-reachable home for gowning must live on one of 8080 / 8090 / 8092. Of these, **8090 is the one
the on-site user has actually confirmed reachable** (OpenProject loads there).

Port-by-port reality:
- **8080** - Apache HTTPS, already hosts gowning, but times out for the on-site user (see above).
- **8090** - Ruby/OpenProject bound to `0.0.0.0:8090` directly (plain HTTP). Reachable on-site.
- **8092** - Apache HTTPS, InvenTree. Could host more apps by path (Apache-fronted), but on-site
  reachability NOT yet tested.

---

## The 8090 wrinkle (why we cannot just add an Alias there)

`ss` proves OpenProject owns `0.0.0.0:8090` directly - Apache's `matc-ssl-8090.conf` vhost is
effectively bypassed (Ruby wins the bind on all interfaces). So adding `Alias /gowning` to the Apache
8090 vhost would NOT work: the request reaches Ruby, never Apache. This is **Option A** below.

(If instead OpenProject had been on `127.0.0.1:8090` with Apache proxying to it - "Option B" - we
could simply add a gowning Alias above the catch-all `ProxyPass "/"` with `ProxyPass /gowning !`.
That is NOT our situation, but it is recorded here in case OpenProject is later moved to localhost.)

---

## Options to make gowning reachable on-site (ranked)

### Option 1 (most robust): put gowning on 443
443 is essentially never blocked by corporate firewalls. There is already a working `*:443` SSL vhost
(`e3server-ssl.conf`) with the same cert. Add a gowning alias to it; reach it at
`https://matcastellas.com/gowning/` with no special port.
- Apache (inside `<VirtualHost *:443>` of `e3server-ssl.conf`):
  ```apache
  Alias /gowning /var/www/html/gowning/public
  <Directory /var/www/html/gowning/public>
      DirectoryIndex index.php
      AllowOverride All
      Require all granted
      Options -Indexes +FollowSymLinks
  </Directory>
  ```
- `.env`: `APP_URL=https://matcastellas.com/gowning` ; keep `SESSION_SECURE_COOKIE=true`.
- Then `sudo -u www-data php artisan optimize:clear`.
- CAVEAT: confirm 443 is open to the segment (almost certainly yes) and that no existing rule on the
  443 vhost (e.g. a catch-all proxy/redirect) shadows `/gowning`. Read `e3server-ssl.conf` first.
- NOTE: 443 was not in IT's "8080/8090/8092" list, but 443 is standard HTTPS and usually already
  open; worth confirming since it sidesteps everything.

### Option 2: share 8090 with OpenProject (Option A path - touches OpenProject)
Because Ruby owns `0.0.0.0:8090`, move OpenProject's listener to a localhost-only port (e.g.
`127.0.0.1:8095`, not firewall-exposed), then let Apache own the public 8090 as a normal multi-app
HTTPS vhost: reverse-proxy `/` to OpenProject on localhost AND add the gowning alias.
- In `matc-ssl-8090.conf` (`<VirtualHost *:8090>`):
  ```apache
  Alias /gowning /var/www/html/gowning/public
  <Directory /var/www/html/gowning/public>
      DirectoryIndex index.php
      AllowOverride All
      Require all granted
      Options -Indexes +FollowSymLinks
  </Directory>
  ProxyPass /gowning !
  ProxyPass        "/" "http://localhost:8095/"   # OpenProject moved here
  ProxyPassReverse "/" "http://localhost:8095/"
  ```
- `.env`: `APP_URL=https://matcastellas.com:8090/gowning` ; `SESSION_SECURE_COOKIE=true`.
- Result: `https://matcastellas.com:8090/` -> OpenProject; `https://.../...:8090/gowning/` -> gowning.
- RISK: requires reconfiguring OpenProject's bind address/port and verifying it still works. Only do
  this if comfortable nudging OpenProject. Back up its config first.

### Option 3: fix 8080 at the firewall (smallest change, keeps current setup)
If IT can genuinely open 8080 to the office segment (and the split-horizon/private-IP path is
accounted for), nothing in the app changes - gowning stays at `https://matcastellas.com:8080/gowning/`.
Depends entirely on IT. Verify with the workstation `curl` afterwards.

### Option 4 (last resort): 8092 alongside InvenTree
8092 is Apache-fronted HTTPS (InvenTree, name-based vhost). Could add a gowning Alias there similarly
to 8080. But on-site reachability of 8092 is UNTESTED, and it mixes gowning into the InvenTree vhost.
Only pursue if 8090/443 are dead ends. Test reachability first.

### NOT viable: plain HTTP on a new port (e.g. 8081, 8094)
We can serve gowning as plain HTTP on a fresh port (mirroring how OpenProject runs naked HTTP on
8090), and it would answer on both IP and domain with no cert warning. BUT a new port must be opened
in the firewall, and IT will only give 8080/8090/8092. 8094 is believed closed. So a new HTTP port is
ruled out by the port constraint.

---

## Protocol/port rules to remember (so we stop re-litigating)
- One PORT speaks one PROTOCOL. 8080 is HTTPS, so it cannot also serve plain HTTP. To offer both
  HTTP and HTTPS you need two different ports - but the firewall only allows three, all currently
  HTTPS-or-occupied, so "HTTP too" is effectively off the table here.
- A vhost can host MANY apps by PATH on one port (this is exactly what 8080 does: MTS, gowning,
  matcproject, draw, excalidraw, GLPI, plus proxies). This is the pattern to reuse.
- `APP_URL` is a SINGLE value and drives Filament's generated asset URLs, redirects, and email links.
  Mixing schemes/ports causes mixed-content breakage. Pick ONE canonical URL.
- `SESSION_SECURE_COOKIE=true` means the login cookie only rides HTTPS; logging in over plain HTTP
  loops. Keep it `true` for the Part 11 system and serve over HTTPS.
- RDS `sslmode=require` in `DB_*` is the DATABASE TLS connection - completely separate from web
  HTTPS. Do not touch it when changing web scheme.

---

## Already-fixed during this investigation (kept for the audit trail)
- **Stale Vite-manifest 500:** every Filament page 500'd on "Vite manifest not found". A good build
  (`public/build/manifest.json`, owned by `www-data`, dated 30-MAY) was actually present; the error
  was stale cache + ownership. Cleared with, as the deploy user:
  ```
  sudo chown -R www-data:www-data public/build storage bootstrap/cache
  sudo -u www-data php artisan optimize:clear
  sudo -u www-data php artisan filament:assets
  ```
  After this, `curl` returns full HTML.
- **EACCES on manual artisan/npm:** running `php artisan ...` or `npm run build` as `matc-web` fails
  (`storage/logs/laravel.log ... Permission denied`; `copy(...js) ... Permission denied`) because the
  tree is owned by `www-data`. FIX: run artisan as `www-data` (or with sudo), and normalize:
  ```
  sudo chown -R www-data:www-data storage bootstrap/cache
  sudo chmod -R 775 storage bootstrap/cache
  ```
- **deploy.sh build guard confirmed working:** `deploy.sh` backs up `public/build` to `build.bak`,
  rebuilds, and restores on failure (so a failed `npm run build` never strips the manifest). The
  earlier 500 was a FIRST-deploy / no-prior-build situation plus stale cache, not the guard failing.

## Still open / to explore in the dedicated chat
- Run the WORKSTATION `curl` tests for 8080 and 8090 (and ideally 8092) to map exactly which allowed
  ports are reachable from the office segment, and capture the timeout vs connect result.
- Decide Option 1 (443) vs Option 2 (share 8090) vs Option 3 (fix 8080 firewall). Recommended order:
  try 443 reachability first (Option 1); if 443 is not open, do Option 2 on the confirmed-reachable
  8090 (accepting the OpenProject reconfig).
- Confirm with IT whether the 8080 "allow" is keyed to the public IP while the office resolves the
  PRIVATE IP via split-horizon DNS (would explain "allowed but times out").
- Read `e3server-ssl.conf` (the `*:443` vhost) fully before adding a gowning alias, to ensure no
  catch-all proxy/redirect shadows `/gowning`.
- Why does the npm BUILD fail on the server originally? (Sandbox could not reproduce a full Filament
  theme build without Composer's installed vendor `dist/` assets.) If a clean server build is wanted,
  diagnose `npm run build` run AS `www-data` (the EACCES was a red herring from running as `matc-web`).
