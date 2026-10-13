@if($currentTheme)
    <style>
        :root {
            --mp-primary: {{ $currentTheme->primary }};
            --mp-primary-rgb: {{ $currentTheme->rgb('primary') }};
            --mp-nav-background: {{ $currentTheme->nav_background }};

            --tblr-link-color: var(--mp-primary);
            --tblr-link-color-rgb: var(--mp-primary-rgb);
            --tblr-link-hover-color: var(--mp-primary);
            --tblr-link-hover-color-rgb: var(--mp-primary-rgb);
        }

        .breadcrumb a {
            --tblr-breadcrumb-link-color: var(--mp-primary);
        }

        .btn-link, .btn-link:hover {
            color: var(--mp-primary);
        }

        .border-primary {
            border-color: var(--mp-primary) !important;
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
