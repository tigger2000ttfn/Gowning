{{-- Gracefully recover from an expired session (HTTP 419 / page expired) instead of
     leaving the user with a broken Livewire action and a console error. When a Livewire
     request comes back 419, the CSRF token is stale (page left open past the session
     lifetime, or a deploy rotated things), so reload the page to get a fresh token. --}}
<script>
    document.addEventListener('livewire:init', () => {
        Livewire.hook('request', ({ fail }) => {
            fail(({ status, preventDefault }) => {
                if (status === 419) {
                    preventDefault();
                    window.location.reload();
                }
            });
        });
    });
</script>
