<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Messages', 'icon' => 'heroicon-o-chat-bubble-left-right'])

    <div class="gqs-tabs">
        @php $unread = $this->unreadCount(); @endphp
        <button type="button" wire:click="$set('tab','inbox')" class="gqs-tab {{ $tab === 'inbox' ? 'on' : '' }}">Inbox @if($unread)<span class="gqs-pill gqs-pill-red" style="margin-left:6px;">{{ $unread }}</span>@endif</button>
        <button type="button" wire:click="$set('tab','sent')" class="gqs-tab {{ $tab === 'sent' ? 'on' : '' }}">Sent</button>
        <button type="button" wire:click="$set('tab','compose')" class="gqs-tab {{ $tab === 'compose' ? 'on' : '' }}">Compose</button>
    </div>

    @if($tab === 'compose')
        <div class="gqs-panel">
            <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-pencil-square"/> New Message</div>
            <div class="gqs-panel-body" style="padding:18px 20px;">
                <label class="gqs-flbl">To</label>
                <select wire:model="toUserId" class="gqs-fld" style="margin-bottom:14px;">
                    <option value="">Select a colleague...</option>
                    @foreach($this->recipientOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                </select>
                <label class="gqs-flbl">Subject</label>
                <input type="text" wire:model="subject" class="gqs-fld" style="margin-bottom:14px;" placeholder="Optional">
                <label class="gqs-flbl">Message</label>
                <textarea wire:model="body" class="gqs-fld" rows="5" placeholder="Write your message..."></textarea>
                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="send" style="padding:9px 18px;border-radius:8px;background:#A4123F;color:#fff;border:none;font-weight:700;cursor:pointer;">Send Message</button>
                </div>
            </div>
        </div>
    @elseif($this->openMessage())
        @php $m = $this->openMessage(); @endphp
        <div class="gqs-panel">
            <div class="gqs-panel-head" style="justify-content:space-between;">
                <span style="display:flex;align-items:center;gap:9px;"><x-filament::icon icon="heroicon-m-envelope-open"/> {{ $m->subject ?: '(No subject)' }}</span>
                <button wire:click="$set('openMessageId', null)" class="gqs-mini-btn">Back</button>
            </div>
            <div class="gqs-panel-body" style="padding:18px 20px;">
                <div style="font-size:13px;color:var(--gqs-text-dim,#6A6A72);margin-bottom:12px;">
                    From <strong>{{ $m->sender?->name }}</strong> to <strong>{{ $m->recipient?->name }}</strong> · {{ $m->created_at?->format('d M Y H:i') }}
                </div>
                <div style="font-size:14px;line-height:1.6;white-space:pre-wrap;color:var(--gqs-text,#1A1A1F);">{{ $m->body }}</div>
                @if($m->sender_id !== auth()->id())
                    <div style="margin-top:18px;">
                        <button wire:click="startReply({{ $m->sender_id }}, '{{ addslashes($m->subject) }}', {{ $m->id }})" style="padding:8px 16px;border-radius:8px;background:#A4123F;color:#fff;border:none;font-weight:700;cursor:pointer;">Reply</button>
                    </div>
                @endif
            </div>
        </div>
    @else
        @php $list = $tab === 'sent' ? $this->sent() : $this->inbox(); @endphp
        <div class="gqs-panel">
            <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-inbox"/> {{ $tab === 'sent' ? 'Sent' : 'Inbox' }}</div>
            <div class="gqs-panel-body">
                @forelse($list as $m)
                    <div wire:click="open({{ $m->id }})" style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid var(--gqs-border,#F2F2F4);cursor:pointer;{{ ($tab==='inbox' && !$m->read_at) ? 'background:rgba(164,18,63,.04);' : '' }}">
                        <div style="min-width:0;">
                            <div style="font-weight:{{ ($tab==='inbox' && !$m->read_at) ? '800' : '600' }};font-size:13.5px;color:var(--gqs-text,#1A1A1F);">
                                {{ $tab === 'sent' ? ('To: ' . $m->recipient?->name) : $m->sender?->name }}
                                @if($tab==='inbox' && !$m->read_at)<span class="gqs-pill gqs-pill-red" style="margin-left:6px;">New</span>@endif
                            </div>
                            <div style="font-size:12.5px;color:var(--gqs-text-dim,#9A9AA4);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:520px;">
                                {{ $m->subject ? $m->subject . ' · ' : '' }}{{ \Illuminate\Support\Str::limit(strip_tags($m->body), 70) }}
                            </div>
                        </div>
                        <span style="font-size:11.5px;color:var(--gqs-text-dim,#B0B0B8);flex:0 0 auto;margin-left:12px;">{{ $m->created_at?->diffForHumans() }}</span>
                    </div>
                @empty
                    <div class="gqs-empty" style="padding:28px;">No Messages.</div>
                @endforelse
            </div>
        </div>
    @endif
</x-filament-panels::page>
