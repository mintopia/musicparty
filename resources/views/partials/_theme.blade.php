@if($currentTheme)
    <style>
        :root {
            --tblr-primary: {{ $currentTheme->primary }};
            --tblr-primary-rgb: {{ $currentTheme->rgb('primary') }};

            --tblr-link-color: {{ $currentTheme->primary }};
            --tblr-link-color-rgb: {{ $currentTheme->rgb('primary') }};
            --tblr-link-hover-color: {{ $currentTheme->primary }};
            --tblr-link-hover-color-rgb: {{ $currentTheme->rgb('primary') }};
        }

        .navbar[data-bs-theme=dark] {
            --tblr-navbar-bg: {{ $currentTheme->nav_background }};
        }

        .breadcrumb a {
            --tblr-breadcrumb-link-color: {{ $currentTheme->primary }};
        }

        .btn-link, .btn-link:hover {
            color: {{ $currentTheme->primary }};
        }

        .border-primary {
            border-color: {{ $currentTheme->primary }} !important;
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
