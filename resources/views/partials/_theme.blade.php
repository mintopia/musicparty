@if($currentTheme)
    <style>
        :root {
            --mp-primary: {{ $currentTheme->primary }};
            --mp-primary-rgb: {{ $currentTheme->rgb('primary') }};
            --mp-nav-background: {{ $currentTheme->nav_background }};
        }

        .seating-plan .seat.available {
            background-color: {{ $currentTheme->seat_available }};
        }

        .seating-plan .seat.disabled {
            background-color: {{ $currentTheme->seat_disabled }};
        }

        .seating-plan .seat.taken {
            background-color: {{ $currentTheme->seat_taken }};
        }

        .seating-plan .seat.seat-clan {
            background-color: {{ $currentTheme->seat_clan }};
        }

        .seating-plan .seat.seat-mine {
            background-color: {{ $currentTheme->seat_selected }};
        }

        /* Theme CSS Override */
        {!! $currentTheme->css !!}
    </style>
@endif
