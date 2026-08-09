{{--
    The product mark: a sheet of paper with a second sheet behind it, and three
    ruled lines where the last one is cut short — the cited passage. Drawn as a
    ring (fill-rule="evenodd") so it inherits `currentColor` and reads at 16px.
--}}
<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" {{ $attributes }}>
    {{-- The sheet behind: "multi-document". --}}
    <rect x="3" y="8" width="3" height="16" rx="1.5" fill="currentColor" opacity="0.45" />

    {{-- The front sheet, as an outline. --}}
    <path
        fill="currentColor"
        fill-rule="evenodd"
        clip-rule="evenodd"
        d="M12 3h13a4 4 0 0 1 4 4v18a4 4 0 0 1-4 4H12a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4Zm0 3a1 1 0 0 0-1 1v18a1 1 0 0 0 1 1h13a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H12Z"
    />

    {{-- Two full lines of text… --}}
    <rect x="14" y="10" width="9" height="2.4" rx="1.2" fill="currentColor" />
    <rect x="14" y="14.8" width="9" height="2.4" rx="1.2" fill="currentColor" />

    {{-- …and the short one: the answer, stopping at its source. --}}
    <rect x="14" y="19.6" width="5.5" height="2.4" rx="1.2" fill="currentColor" opacity="0.55" />
</svg>
