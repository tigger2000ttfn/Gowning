@php
    use App\Enums\Capability;
    $announcements = \App\Models\Announcement::where('is_active', true)->latest()->limit(8)->get();
    $canPost = auth()->user()?->hasCapability(Capability::ManageClasses)
        || auth()->user()?->hasCapability(Capability::ManageUsers);
    $unreadMsgs = \App\Models\Message::where('recipient_id', auth()->id())->whereNull('read_at')->count();
    $msgUrl = \App\Filament\Admin\Pages\Messages::getUrl();
@endphp
{{-- Direct messages icon (links to the Messages page) --}}
<a href="{{ $msgUrl }}" class="fi-icon-btn fi-size-md" title="Messages"
   style="position:relative;display:flex;align-items:center;justify-content:center;width:2.25rem;height:2.25rem;text-decoration:none;">
    <x-filament::icon icon="heroicon-o-chat-bubble-left-right" style="width:1.25rem;height:1.25rem;color:#ECECF0;" />
    @if($unreadMsgs)
        <span style="position:absolute;top:2px;right:2px;min-width:15px;height:15px;padding:0 3px;border-radius:8px;background:#C8102E;color:#fff;font-size:9.5px;font-weight:800;display:flex;align-items:center;justify-content:center;line-height:1;">{{ $unreadMsgs > 9 ? '9+' : $unreadMsgs }}</span>
    @endif
</a>
<div x-data="{ open: false }" class="gqs-msg" style="position:relative;">
    <button @click="open = !open" type="button" class="fi-icon-btn fi-size-md" title="Announcements"
            style="position:relative;display:flex;align-items:center;justify-content:center;width:2.25rem;height:2.25rem;">
        <x-filament::icon icon="heroicon-o-megaphone" style="width:1.25rem;height:1.25rem;color:#ECECF0;" />
        @if($announcements->isNotEmpty())
            <span style="position:absolute;top:5px;right:5px;width:8px;height:8px;border-radius:50%;background:#E8C24A;"></span>
        @endif
    </button>
    <div x-show="open" x-transition.origin.top.right @click.outside="open = false" x-cloak
         class="gqs-msg-menu"
         style="position:absolute;right:0;top:calc(100% + 8px);width:340px;max-height:420px;overflow-y:auto;
                background:#fff;border:1px solid #DADADF;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.18);z-index:50;padding:8px;">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 10px 10px;border-bottom:1px solid #EEE;">
            <span style="font-weight:800;font-size:14px;color:#1A1A1F;">Announcements</span>
            @if($canPost)
                <a href="{{ \App\Filament\Admin\Resources\AnnouncementResource::getUrl() }}"
                   style="font-size:12px;font-weight:700;color:#A4123F;text-decoration:none;">Manage</a>
            @endif
        </div>
        @forelse($announcements as $a)
            <div style="padding:11px 10px;border-bottom:1px solid #F2F2F4;">
                <div style="font-weight:700;font-size:13.5px;color:#1A1A1F;">{{ $a->title }}</div>
                <div style="font-size:12.5px;color:#5A5A62;margin-top:3px;line-height:1.45;">{{ \Illuminate\Support\Str::limit($a->body, 140) }}</div>
                <div style="font-size:11px;color:#9A9AA4;margin-top:5px;">{{ $a->author_name ?? 'System' }} · {{ $a->created_at?->diffForHumans() }}</div>
            </div>
        @empty
            <div style="padding:18px 10px;text-align:center;color:#9A9AA4;font-size:13px;">No Announcements.</div>
        @endforelse
    </div>
</div>
