@php $isTime = $isTime ?? false; $live = $live ?? false; @endphp
{{-- GMP date/time field. flatpickr enhances the input to show DD MMM YYYY / 24h while
     keeping an ISO value bound to Livewire. wire:ignore keeps Livewire from re-morphing
     flatpickr's DOM; $wire.watch keeps the field in sync when the server sets the value
     (modal prefill / reset). If flatpickr fails to load, the native input remains. --}}
<div wire:ignore x-data x-init="
    (function () {
        const el = $refs.fpi;
        if (!window.flatpickr) return; // graceful fallback to native input
        const fp = flatpickr(el, {
            @if($isTime)
            enableTime: true, noCalendar: true, dateFormat: 'H:i', time_24hr: true,
            @else
            dateFormat: 'Y-m-d', altInput: true, altFormat: 'd-M-Y', altInputClass: 'gqs-fld gqs-gmp-up',
            @endif
            allowInput: true,
            defaultDate: $wire.get('{{ $model }}') || null,
            onChange: function (sel, str) { $wire.set('{{ $model }}', str{{ $live ? '' : ', false' }}); }
        });
        try {
            $wire.watch('{{ $model }}', function (val) {
                if ((val || '') !== el.value) fp.setDate(val || null, false);
            });
        } catch (e) {}
    })();
">
    <input x-ref="fpi" type="{{ $isTime ? 'time' : 'date' }}" class="gqs-fld">
</div>
