<?php
function icon(string $name,string $class=''):string
{
    $paths=[
        'dashboard'=>'<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
        'list'=>'<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'up'=>'<path d="M7 17 17 7M7 7h10v10"/>','down'=>'<path d="m7 7 10 10M7 17h10V7"/>',
        'transfer'=>'<path d="M3 7h17l-4-4M21 17H4l4 4"/>','wallet'=>'<path d="M20 8V5a2 2 0 0 0-2-2H6a3 3 0 0 0 0 6h15v12H6a3 3 0 0 1-3-3V6"/><path d="M21 12h-5v5h5"/>',
        'card'=>'<rect x="2" y="4" width="20" height="16" rx="3"/><path d="M2 10h20M6 16h3"/>',
        'receipt'=>'<path d="M5 3h14v19l-3-2-4 2-4-2-3 2V3ZM8 8h8M8 12h8M8 16h4"/>',
        'budget'=>'<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 11h2M14 11h2M8 15h2M14 15h2"/>',
        'calendar'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 11h18M7 15h3M14 15h3"/>',
        'repeat'=>'<path d="m17 2 4 4-4 4M3 11V8a2 2 0 0 1 2-2h16M7 22l-4-4 4-4m14-1v3a2 2 0 0 1-2 2H3"/>',
        'layers'=>'<path d="m12 3 10 5-10 5L2 8l10-5Zm-10 9 10 5 10-5M2 16l10 5 10-5"/>',
        'target'=>'<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
        'bank'=>'<path d="m3 9 9-6 9 6H3Zm0 12h18M6 12v6M12 12v6M18 12v6"/>','chart'=>'<path d="M3 3v18h18M7 16v-4M12 16V7M17 16v-7"/>',
        'upload'=>'<path d="M12 16V3m-5 5 5-5 5 5M3 16v5h18v-5"/>','settings'=>'<path d="M4 7h16M4 17h16"/><circle cx="8" cy="7" r="3"/><circle cx="16" cy="17" r="3"/>',
        'tag'=>'<path d="M3 3h8l10 10-8 8L3 11V3Z"/><circle cx="7" cy="7" r="1"/>','store'=>'<path d="M3 9h18l-2-6H5L3 9Zm2 0v12h14V9M9 21v-7h6v7"/>',
        'plus'=>'<path d="M12 5v14M5 12h14"/>','search'=>'<circle cx="10" cy="10" r="6"/><path d="m15 15 6 6"/>','chevron'=>'<path d="m9 5 7 7-7 7"/>',
        'menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>','logout'=>'<path d="M9 4H4v16h5m7-13 5 5-5 5M9 12h12"/>','shield'=>'<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/>',
        'check'=>'<path d="m5 12 4 4L19 6"/>','close'=>'<path d="m6 6 12 12M6 18 18 6"/>','edit'=>'<path d="m15 4 5 5M4 20l5-1L21 7l-4-4L5 15l-1 5Z"/>','trash'=>'<path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7"/>',
    ];
    return '<svg class="icon '.e($class).'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['dashboard']).'</svg>';
}
