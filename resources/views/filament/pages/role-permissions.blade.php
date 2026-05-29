<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Roles & Permissions', 'subtitle' => 'Toggle what each role can do. Changes take effect immediately. Super User always has full access.', 'icon' => 'heroicon-o-shield-check'])

    <style>
        .perm-wrap{overflow-x:auto;border:1px solid var(--gqs-border,#DADADF);border-radius:14px;background:var(--gqs-surface,#fff);}
        .perm-tbl{border-collapse:separate;border-spacing:0;width:100%;font-size:13px;}
        .perm-tbl th,.perm-tbl td{padding:9px 10px;text-align:center;border-bottom:1px solid var(--gqs-border,#EEE);}
        .perm-tbl thead th{position:sticky;top:0;background:#15151A;color:#fff;font-weight:600;font-size:11px;z-index:2;}
        .perm-tbl thead th.grp{background:#A4123F;font-size:10px;text-transform:uppercase;letter-spacing:.04em;}
        .perm-role{position:sticky;left:0;background:var(--gqs-surface,#fff);text-align:left !important;font-weight:700;color:var(--gqs-text,#1A1A1F);white-space:nowrap;border-right:2px solid #A4123F;z-index:1;}
        .perm-super{color:#A4123F;}
        .perm-tbl input[type=checkbox]{width:17px;height:17px;accent-color:#A4123F;cursor:pointer;}
        .perm-tbl input:disabled{accent-color:#2E7D5B;cursor:not-allowed;}
        .perm-tbl tbody tr:hover{background:rgba(164,18,63,.04);}
    </style>

    <form wire:submit="save">
        <div class="perm-wrap">
            <table class="perm-tbl">
                <thead>
                    @php $groups = collect($this->capabilities())->groupBy(fn($c)=>$c->group()); @endphp
                    <tr>
                        <th class="perm-role">Role</th>
                        @foreach ($groups as $gname => $caps)
                            <th class="grp" colspan="{{ count($caps) }}">{{ $gname }}</th>
                        @endforeach
                    </tr>
                    <tr>
                        <th class="perm-role"></th>
                        @foreach ($this->capabilities() as $cap)
                            <th>{{ $cap->label() }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->roles() as $role)
                        <tr>
                            <td class="perm-role {{ $role->isSuperUser() ? 'perm-super' : '' }}">{{ $role->label() }}</td>
                            @foreach ($this->capabilities() as $cap)
                                <td>
                                    <input type="checkbox"
                                        wire:model="matrix.{{ $role->value }}.{{ $cap->value }}"
                                        @if($role->isSuperUser()) checked disabled @endif>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="margin-top:18px;">
            <x-filament::button type="submit" icon="heroicon-m-check">Save Permissions</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
