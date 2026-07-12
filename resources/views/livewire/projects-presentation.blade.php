<x-ui-page>
    @include('planner::partials.planner-tokens')

    <x-slot name="navbar">
        <x-ui-page-navbar title="Präsentation" icon="heroicon-o-presentation-chart-line" />
    </x-slot>

    @verbatim
    <style>
        .pm {
            --ink: #16202A; --ink-soft: #3A4652; --muted: #6A7683;
            --ground: #EDF0F3; --panel: #FFFFFF; --line: #E1E6EB; --line-strong: #CDD5DD;
            --accent: #0F5F5A; --accent-ink: #0A4744; --accent-soft: #E3F0EE;
            --good: #2E7D5B; --good-soft: #E4F1EA; --warn: #C77A2B; --warn-soft: #FAF0E3;
            --serif: "Iowan Old Style", "Palatino Linotype", Palatino, "Book Antiqua", Georgia, serif;
            --shadow-soft: 0 1px 2px rgba(16,32,42,.04);
            background: var(--ground); color: var(--ink);
        }
        .pm * { box-sizing: border-box; }

        /* client bar */
        .pm-clientbar { display: flex; align-items: center; gap: 16px; padding: 12px 28px; background: var(--panel); border-bottom: 1px solid var(--line); }
        .pm-back { display: inline-flex; align-items: center; gap: 7px; font-size: 13px; color: var(--muted); background: none; border: 0; cursor: pointer; padding: 6px 8px; border-radius: 8px; font-family: inherit; }
        .pm-back:hover { color: var(--ink); background: var(--ground); }
        .pm-client { margin: 0 auto; display: flex; align-items: center; gap: 11px; }
        .pm-mark { width: 30px; height: 30px; border-radius: 8px; background: var(--accent); color: #fff; display: grid; place-items: center; font-family: var(--serif); font-size: 16px; font-weight: 600; }
        .pm-client .name { font-size: 15px; font-weight: 600; }
        .pm-client .sub { font-size: 11px; color: var(--muted); }
        .pm-counter { font-size: 12px; color: var(--muted); font-variant-numeric: tabular-nums; white-space: nowrap; }

        /* body split */
        .pm-body { display: grid; grid-template-columns: 272px 1fr; min-height: 0; flex: 1; }
        .pm-rail { background: var(--panel); border-right: 1px solid var(--line); overflow-y: auto; padding: 16px 14px; }
        .pm-rail h2 { font-size: 10.5px; text-transform: uppercase; letter-spacing: .09em; color: var(--muted); font-weight: 700; margin: 4px 6px 12px; }
        .pm-navlist { display: flex; flex-direction: column; gap: 4px; }
        .pm-navitem { text-align: left; width: 100%; background: none; border: 0; cursor: pointer; font-family: inherit; padding: 11px 12px 11px 13px; border-radius: 10px; border-left: 3px solid transparent; display: flex; flex-direction: column; gap: 8px; color: var(--ink-soft); }
        .pm-navitem:hover { background: var(--ground); }
        .pm-navitem.active { background: var(--accent-soft); border-left-color: var(--accent); color: var(--accent-ink); }
        .pm-navitem .top { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; }
        .pm-navitem .t { font-size: 13.5px; font-weight: 600; line-height: 1.25; }
        .pm-navitem .pct { font-size: 11px; color: var(--muted); font-variant-numeric: tabular-nums; flex-shrink: 0; }
        .pm-navitem.active .pct { color: var(--accent-ink); }
        .pm-minibar { height: 4px; border-radius: 3px; background: var(--line); overflow: hidden; }
        .pm-minibar > span { display: block; height: 100%; border-radius: 3px; background: var(--accent); }

        /* stage */
        .pm-stagewrap { overflow-y: auto; min-height: 0; }
        .pm-stage { max-width: none; margin: 0; padding: 30px 40px 40px; display: flex; flex-direction: column; gap: 22px; }
        .pm-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 24px; }
        .pm-head h1 { font-family: var(--serif); font-weight: 600; font-size: 40px; line-height: 1.05; margin: 0; letter-spacing: -.01em; text-wrap: balance; color: var(--ink); }
        .pm-head .meta { margin-top: 8px; font-size: 13.5px; color: var(--muted); }
        .pm-chip { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; padding: 5px 11px; border-radius: 999px; background: var(--good-soft); color: var(--good); white-space: nowrap; }
        .pm-chip .dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
        .pm-chip.neutral { background: var(--ground); color: var(--ink-soft); }
        .pm-chip.warn { background: var(--warn-soft); color: var(--warn); }
        .pm-chip.trend { background: var(--ground); color: var(--ink-soft); }
        .pm-chip.trend .tdot { width: 8px; height: 8px; border-radius: 50%; }
        .pm-chips { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; justify-content: flex-end; flex-shrink: 0; }

        .pm-tiles { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .pm-tile { background: var(--panel); border: 1px solid var(--line); border-radius: 14px; padding: 20px 22px; box-shadow: var(--shadow-soft); display: flex; align-items: center; gap: 20px; }
        .pm-tile.plain { display: block; }
        .pm-tile .label { font-size: 10.5px; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); font-weight: 700; margin-bottom: 7px; }
        .pm-tile .big { font-size: 26px; font-weight: 650; letter-spacing: -.01em; font-variant-numeric: tabular-nums; line-height: 1; }
        .pm-tile .big small { font-size: 14px; font-weight: 500; color: var(--muted); margin-left: 2px; }
        .pm-tile .note { font-size: 12px; color: var(--muted); margin-top: 6px; }
        .pm-tile .note.warn { color: var(--warn); font-weight: 600; }

        .pm-ring { position: relative; width: 74px; height: 74px; flex-shrink: 0; }
        .pm-ring::before { content: ""; position: absolute; inset: 0; border-radius: 50%; background: conic-gradient(var(--c) calc(var(--p) * 1%), var(--line) 0); -webkit-mask: radial-gradient(closest-side, transparent 68%, #000 69%); mask: radial-gradient(closest-side, transparent 68%, #000 69%); }
        .pm-ring .val { position: absolute; inset: 0; display: grid; place-items: center; font-size: 15px; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--ink); }

        .pm-lower { display: grid; grid-template-columns: 1.55fr 1fr; gap: 16px; align-items: start; }
        .pm-panel { background: var(--panel); border: 1px solid var(--line); border-radius: 14px; box-shadow: var(--shadow-soft); overflow: hidden; }
        .pm-panel > header { display: flex; align-items: center; justify-content: space-between; padding: 15px 20px; border-bottom: 1px solid var(--line); }
        .pm-panel > header .h { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); font-weight: 700; }
        .pm-panel > header .agg { font-size: 12.5px; color: var(--muted); font-variant-numeric: tabular-nums; }
        .pm-panel .pad { padding: 8px 20px 18px; }

        .pm-task { padding: 14px 0; border-bottom: 1px solid var(--line); }
        .pm-task:last-child { border-bottom: 0; }
        .pm-task .row { display: flex; align-items: center; gap: 10px; }
        .pm-task .tt { font-size: 15px; font-weight: 600; color: var(--ink); }
        .pm-check { width: 22px; height: 22px; border-radius: 6px; border: 1.5px solid var(--line-strong); background: #fff; cursor: pointer; flex-shrink: 0; display: grid; place-items: center; color: transparent; padding: 0; transition: border-color .12s, background .12s, color .12s; }
        .pm-check:hover { border-color: var(--good); background: var(--good-soft); color: var(--good); }
        .pm-pill { margin-left: auto; font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 999px; background: var(--ground); color: var(--muted); font-variant-numeric: tabular-nums; white-space: nowrap; }
        .pm-pill.done { background: var(--good-soft); color: var(--good); }
        .pm-dods { margin: 10px 0 2px; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 7px; }
        .pm-dods li { display: flex; align-items: flex-start; gap: 10px; font-size: 13.5px; color: var(--ink-soft); line-height: 1.4; }
        .pm-box { width: 15px; height: 15px; border-radius: 4px; border: 1.5px solid var(--line-strong); margin-top: 2px; flex-shrink: 0; }
        .pm-task .none { font-size: 12.5px; color: var(--muted); font-style: italic; margin-top: 4px; }
        .pm-empty-good { padding: 26px 20px; text-align: center; color: var(--good); font-size: 14px; font-weight: 600; }

        .pm-canvas { display: flex; flex-direction: column; gap: 14px; }
        .pm-split { display: grid; grid-template-columns: minmax(0, 1fr) 380px; gap: 16px; align-items: start; }
        .pm-side { position: sticky; top: 0; align-self: start; display: flex; flex-direction: column; gap: 14px; }
        .pm-cvcard { background: var(--panel); border: 1px solid var(--line); border-radius: 14px; box-shadow: var(--shadow-soft); padding: 16px 18px; }
        .pm-cvcard .lbl { font-size: 10.5px; text-transform: uppercase; letter-spacing: .08em; color: var(--accent); font-weight: 700; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
        .pm-cvcard .lbl::after { content: ""; flex: 1; height: 1px; background: var(--line); }
        .pm-cventry { margin-bottom: 11px; }
        .pm-cventry:last-child { margin-bottom: 0; }
        .pm-cventry .et { font-size: 14px; font-weight: 600; color: var(--ink); line-height: 1.3; }
        .pm-cventry .ec { font-size: 12.5px; color: var(--muted); line-height: 1.45; margin-top: 3px; }

        .pm-footer { display: flex; align-items: center; gap: 16px; padding: 12px 28px; background: var(--panel); border-top: 1px solid var(--line); }
        .pm-navbtn { display: inline-flex; align-items: center; gap: 6px; font-family: inherit; font-size: 13px; font-weight: 600; cursor: pointer; padding: 8px 14px; border-radius: 9px; border: 1px solid var(--line-strong); background: var(--panel); color: var(--ink); }
        .pm-navbtn:hover { background: var(--ground); }
        .pm-navbtn.primary { background: var(--accent); border-color: var(--accent); color: #fff; }
        .pm-navbtn.primary:hover { background: var(--accent-ink); }
        .pm-navbtn:disabled { opacity: .4; cursor: not-allowed; }
        .pm-dots { display: flex; gap: 8px; margin: 0 auto; align-items: center; }
        .pm-dot { width: 9px; height: 9px; border-radius: 50%; border: 0; padding: 0; cursor: pointer; background: var(--line-strong); transition: transform .15s; }
        .pm-dot.active { background: var(--accent); transform: scale(1.35); }
        .pm-kbd { font-size: 11px; color: var(--muted); }
        .pm-kbd b { background: var(--ground); border: 1px solid var(--line); border-radius: 5px; padding: 1px 6px; font-weight: 600; font-size: 11px; }

        /* chooser */
        .pm-chooser { max-width: 760px; width: 100%; margin: 0 auto; padding: 44px 24px; }
        .pm-chooser h1 { font-family: var(--serif); font-size: 30px; font-weight: 600; margin: 0 0 6px; letter-spacing: -.01em; }
        .pm-chooser .lead { font-size: 14px; color: var(--muted); margin: 0 0 22px; }
        .pm-search { width: 100%; font-size: 14px; border-radius: 10px; border: 1px solid var(--line-strong); padding: 11px 14px; margin-bottom: 16px; outline: none; font-family: inherit; }
        .pm-search:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
        .pm-elist { display: flex; flex-direction: column; gap: 8px; }
        .pm-ecard { display: flex; align-items: center; gap: 14px; width: 100%; text-align: left; background: var(--panel); border: 1px solid var(--line); border-radius: 12px; padding: 14px 16px; box-shadow: var(--shadow-soft); cursor: pointer; font-family: inherit; }
        .pm-ecard:hover { border-color: var(--accent); }
        .pm-ecard .ico { width: 38px; height: 38px; border-radius: 10px; background: var(--accent-soft); color: var(--accent); display: grid; place-items: center; flex-shrink: 0; font-family: var(--serif); font-size: 18px; font-weight: 600; }
        .pm-ecard .en { font-size: 15px; font-weight: 600; color: var(--ink); }
        .pm-ecard .ec { font-size: 11.5px; color: var(--muted); }
        .pm-ecard .arr { margin-left: auto; color: var(--muted); }
        .pm-blank { padding: 44px 20px; text-align: center; border: 1px dashed var(--line-strong); border-radius: 12px; background: var(--panel); }
        .pm-blank h3 { font-size: 16px; margin: 10px 0 4px; }
        .pm-blank p { font-size: 13.5px; color: var(--muted); margin: 0; }
        .pm-center { flex: 1; display: grid; place-items: center; text-align: center; padding: 48px; }
        .pm-ecard-links { display: flex; flex-wrap: wrap; gap: 3px 12px; margin-top: 5px; }
        .pm-eclink { font-size: 11.5px; color: var(--ink-soft); }
        .pm-eclink b { color: var(--muted); font-weight: 600; }

        /* overview */
        .pm-bezuege { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
        .pm-bezug { background: var(--panel); border: 1px solid var(--line); border-radius: 14px; box-shadow: var(--shadow-soft); padding: 16px 18px; }
        .pm-bezug .lbl { font-size: 10.5px; text-transform: uppercase; letter-spacing: .08em; color: var(--accent); font-weight: 700; margin-bottom: 6px; }
        .pm-bezug .val { font-size: 18px; font-weight: 600; color: var(--ink); font-family: var(--serif); }
        .pm-healthbar { display: flex; height: 12px; border-radius: 6px; overflow: hidden; background: var(--line); }
        .pm-healthbar > span { display: block; }
        .pm-hmix { display: flex; gap: 16px; flex-wrap: wrap; margin-top: 11px; font-size: 12px; color: var(--muted); }
        .pm-hmix .k { display: inline-flex; align-items: center; gap: 5px; }
        .pm-hmix .k .d { width: 9px; height: 9px; border-radius: 50%; }
        .pm-prjrow { display: grid; grid-template-columns: 1fr auto auto auto auto; gap: 16px; align-items: center; padding: 12px 4px; border: 0; border-bottom: 1px solid var(--line); background: none; cursor: pointer; font-family: inherit; text-align: left; width: 100%; }
        .pm-prjrow:last-child { border-bottom: 0; }
        .pm-prjrow:hover { background: var(--ground); }
        .pm-prjrow .pn { font-size: 14.5px; font-weight: 600; color: var(--ink); }
        .pm-prjrow .m { font-size: 12.5px; color: var(--muted); font-variant-numeric: tabular-nums; white-space: nowrap; }
        .pm-prjrow .hd { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }

        @media (max-width: 1100px) { .pm-tiles { grid-template-columns: 1fr; } .pm-lower { grid-template-columns: 1fr; } .pm-split { grid-template-columns: 1fr; } .pm-side { position: static; } }
        @media (prefers-reduced-motion: reduce) { .pm * { transition: none !important; } }
    </style>
    @endverbatim

    @php
        $fmtHours = fn ($min) => number_format(((int) $min) / 60, 1, ',', '.');
    @endphp

    {{-- ═══════════ ZUSTAND A · Engagement-Auswahl ═══════════ --}}
    @if(! $engagementId)
        <div class="pm flex-1 flex flex-col min-h-0 overflow-y-auto">
            <div class="pm-chooser">
                <h1>Mit welchem Kunden gehst du durch?</h1>
                <p class="lead">Wähle ein Engagement — du bekommst dann dessen laufende Projekte als ruhige, durchklickbare Slides.</p>

                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Engagement suchen …" class="pm-search" autofocus />

                <div class="pm-elist">
                    @forelse($this->engagements as $e)
                        <button type="button" wire:click="selectEngagement({{ $e['id'] }})" class="pm-ecard">
                            <span class="ico">{{ mb_strtoupper(mb_substr($e['name'], 0, 1)) }}</span>
                            <span style="flex:1; min-width:0">
                                <span class="en" style="display:block">{{ $e['name'] }}</span>
                                <span class="ec">{{ $e['count'] }} {{ $e['count'] === 1 ? 'laufendes Projekt' : 'laufende Projekte' }}</span>
                                @if(! empty($e['links']))
                                    <span class="pm-ecard-links">
                                        @foreach($e['links'] as $lk)
                                            <span class="pm-eclink"><b>{{ $lk['label'] }}:</b> {{ $lk['name'] }}</span>
                                        @endforeach
                                    </span>
                                @endif
                            </span>
                            <span class="arr">@svg('heroicon-o-arrow-right', 'w-4 h-4')</span>
                        </button>
                    @empty
                        <div class="pm-blank">
                            @svg('heroicon-o-briefcase', 'w-7 h-7', ['style' => 'color:var(--muted)'])
                            <h3>Keine Engagements mit laufenden Projekten</h3>
                            <p>{{ $search !== '' ? 'Keine Treffer — Suche anpassen.' : 'Sobald Projekte an einem Engagement hängen, erscheinen sie hier.' }}</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

    {{-- ═══════════ ZUSTAND B · Präsentation ═══════════ --}}
    @else
        @php
            $slides = $this->slides;
            $projectCount = count($slides);
            $isOverview = ($index === 0);
            $current = $isOverview ? null : ($slides[$index - 1] ?? null);
            $ov = $this->overview;
        @endphp

        <div class="pm flex-1 flex flex-col min-h-0"
             x-data
             @keydown.arrow-right.window="$wire.next()"
             @keydown.arrow-left.window="$wire.prev()">

            {{-- Client-Leiste --}}
            <div class="pm-clientbar">
                <button class="pm-back" wire:click="exitPresentation" title="Kunde wechseln">
                    @svg('heroicon-o-arrow-left', 'w-4 h-4') Kunde wechseln
                </button>
                <div class="pm-client">
                    <div class="pm-mark">{{ mb_strtoupper(mb_substr($this->engagementName ?? 'E', 0, 1)) }}</div>
                    <div>
                        <div class="name">{{ $this->engagementName ?? 'Engagement' }}</div>
                        <div class="sub">Engagement · laufende Projekte</div>
                    </div>
                </div>
                <div class="pm-counter">{{ $isOverview ? 'Überblick' : 'Projekt ' . $index . ' / ' . $projectCount }}</div>
            </div>

            @if(! $isOverview && ! $current)
                <div class="pm-center">
                    <div>
                        @svg('heroicon-o-folder-open', 'w-8 h-8', ['style' => 'color:var(--muted)'])
                        <h3 style="margin:10px 0 4px;font-size:16px">Keine laufenden Projekte</h3>
                        <p style="margin:0;color:var(--muted);font-size:13.5px">Für dieses Engagement gibt es gerade nichts durchzusprechen.</p>
                    </div>
                </div>
            @else
                <div class="pm-body">
                    {{-- Navigator: Überblick + Projekte --}}
                    <aside class="pm-rail">
                        <h2>Engagement</h2>
                        <div class="pm-navlist">
                            <button type="button" wire:click="goTo(0)" class="pm-navitem {{ $isOverview ? 'active' : '' }}">
                                <div class="top"><span class="t">Überblick</span></div>
                            </button>
                        </div>
                        <h2 style="margin-top:14px">Projekte</h2>
                        <div class="pm-navlist">
                            @foreach($slides as $i => $s)
                                @php $pct = $s['dod_total'] > 0 ? round($s['dod_checked'] / $s['dod_total'] * 100) : 0; @endphp
                                <button type="button" wire:click="goTo({{ $i + 1 }})" class="pm-navitem {{ $index === $i + 1 ? 'active' : '' }}">
                                    <div class="top">
                                        <span class="t">{{ $s['name'] }}</span>
                                        <span class="pct">{{ $s['dod_total'] > 0 ? $pct . '%' : '—' }}</span>
                                    </div>
                                    <div class="pm-minibar"><span style="width: {{ $pct }}%"></span></div>
                                </button>
                            @endforeach
                        </div>
                    </aside>

                    {{-- Bühne --}}
                    <div class="pm-stagewrap" wire:key="stage-{{ $isOverview ? 'overview' : $current['id'] }}">
                        <div class="pm-stage">
                        @if($isOverview)
                            {{-- ══ Engagement-Überblick ══ --}}
                            <div class="pm-head">
                                <div>
                                    <h1>Überblick</h1>
                                    <div class="meta">{{ $ov['project_count'] }} {{ $ov['project_count'] === 1 ? 'laufendes Projekt' : 'laufende Projekte' }}</div>
                                </div>
                            </div>

                            {{-- Bezüge: Venture / Kunde (aus Dimension-Links) --}}
                            @if(! empty($ov['links']))
                                <div class="pm-bezuege">
                                    @foreach($ov['links'] as $lk)
                                        <div class="pm-bezug">
                                            <div class="lbl">{{ $lk['label'] }}</div>
                                            <div class="val">{{ $lk['name'] }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Aggregat-Kacheln --}}
                            @php
                                $ovTimePct = $ov['planned_minutes'] > 0 ? min(100, round($ov['logged_minutes'] / $ov['planned_minutes'] * 100)) : null;
                                $ovOver = $ov['planned_minutes'] > 0 && $ov['logged_minutes'] > $ov['planned_minutes'];
                                $ovDodPct = $ov['dod_total'] > 0 ? round($ov['dod_checked'] / $ov['dod_total'] * 100) : null;
                                $nd = $ov['nearest_deadline'];
                            @endphp
                            <div class="pm-tiles">
                                <div class="pm-tile">
                                    <div class="pm-ring" style="--p: {{ $ovTimePct ?? 0 }}; --c: {{ $ovOver ? 'var(--warn)' : 'var(--accent)' }}">
                                        <span class="val">{{ $ovTimePct !== null ? $ovTimePct . '%' : '–' }}</span>
                                    </div>
                                    <div>
                                        <div class="label">Zeit gesamt</div>
                                        <div class="big">{{ $fmtHours($ov['logged_minutes']) }}<small> h investiert</small></div>
                                        <div class="note {{ $ovOver ? 'warn' : '' }}">{{ $ov['planned_minutes'] > 0 ? ($ovOver ? 'über ' . $fmtHours($ov['planned_minutes']) . ' h geplant' : 'von ' . $fmtHours($ov['planned_minutes']) . ' h geplant') : 'keine Planung hinterlegt' }}</div>
                                    </div>
                                </div>
                                <div class="pm-tile">
                                    <div class="pm-ring" style="--p: {{ $ovDodPct ?? 0 }}; --c: var(--good)">
                                        <span class="val">{{ $ovDodPct !== null ? $ovDodPct . '%' : '–' }}</span>
                                    </div>
                                    <div>
                                        <div class="label">Fortschritt gesamt</div>
                                        <div class="big">{{ $ov['dod_checked'] }}<small> / {{ $ov['dod_total'] }} Kriterien</small></div>
                                        <div class="note">über alle Projekte</div>
                                    </div>
                                </div>
                                <div class="pm-tile plain">
                                    <div class="label">Offene Aufgaben</div>
                                    <div class="big">{{ $ov['open_tasks'] }}</div>
                                    <div class="note {{ $ov['overdue'] > 0 ? 'warn' : '' }}">{{ $ov['overdue'] > 0 ? $ov['overdue'] . ' überfällig' : 'nichts überfällig' }}</div>
                                </div>
                            </div>

                            {{-- Projekt-Liste + Status-Verteilung --}}
                            <div class="pm-lower">
                                <div class="pm-panel">
                                    <header><span class="h">Projekte</span><span class="agg">{{ $ov['project_count'] }} laufend</span></header>
                                    <div class="pad">
                                        @forelse($slides as $i => $s)
                                            @php
                                                $rowPct = $s['dod_total'] > 0 ? round($s['dod_checked'] / $s['dod_total'] * 100) : null;
                                                $rowHc = match($s['health_color']) { 'red' => '#DC2626', 'yellow' => '#D97706', 'green' => '#2E7D5B', default => '#94A3B8' };
                                            @endphp
                                            <button type="button" wire:click="goTo({{ $i + 1 }})" class="pm-prjrow">
                                                <span class="pn">{{ $s['name'] }}</span>
                                                <span class="m">{{ $fmtHours($s['logged_minutes']) }} h</span>
                                                <span class="m">{{ $s['planned_end'] ?? '—' }}</span>
                                                <span class="m">{{ $rowPct !== null ? $rowPct . '%' : '—' }}</span>
                                                <span class="hd" style="background: {{ $rowHc }}" title="Status"></span>
                                            </button>
                                        @empty
                                            <div class="pm-empty-good" style="color:var(--muted)">Noch keine laufenden Projekte.</div>
                                        @endforelse
                                    </div>
                                </div>

                                <div class="pm-canvas">
                                    @if($nd)
                                        <div class="pm-cvcard">
                                            <div class="lbl">Nächste Deadline</div>
                                            <div class="pm-cventry">
                                                <div class="et">{{ $nd['date'] }} · {{ $nd['name'] }}</div>
                                                <div class="ec">{{ $nd['days'] >= 0 ? 'noch ' . $nd['days'] . ' Tage' : abs($nd['days']) . ' Tage über Termin' }}</div>
                                            </div>
                                        </div>
                                    @endif
                                    @php
                                        $mix = $ov['health_mix'];
                                        $mixTotal = max(1, array_sum($mix));
                                        $mixDefs = ['green' => ['#2E7D5B', 'Grün'], 'yellow' => ['#D97706', 'Gelb'], 'red' => ['#DC2626', 'Rot'], 'gray' => ['#94A3B8', 'Offen']];
                                    @endphp
                                    <div class="pm-cvcard">
                                        <div class="lbl">Status-Verteilung</div>
                                        <div class="pm-healthbar">
                                            @foreach($mixDefs as $k => $d)
                                                @if(($mix[$k] ?? 0) > 0)
                                                    <span style="width: {{ round(($mix[$k] / $mixTotal) * 100) }}%; background: {{ $d[0] }}"></span>
                                                @endif
                                            @endforeach
                                        </div>
                                        <div class="pm-hmix">
                                            @foreach($mixDefs as $k => $d)
                                                @if(($mix[$k] ?? 0) > 0)
                                                    <span class="k"><span class="d" style="background: {{ $d[0] }}"></span>{{ $d[1] }} {{ $mix[$k] }}</span>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            {{-- Kopf --}}
                            @php
                                $days = $current['days_to_end'];
                                $hcColor = match($current['health_color']) {
                                    'red' => '#DC2626', 'yellow' => '#D97706', 'green' => '#2E7D5B', default => '#94A3B8',
                                };
                            @endphp
                            <div class="pm-head">
                                <div>
                                    <h1>{{ $current['name'] }}</h1>
                                    @php
                                        $metaParts = array_filter([
                                            $current['owner_name'] ? 'Verantwortlich · ' . $current['owner_name'] : null,
                                            $current['created_at'] ? 'angelegt am ' . $current['created_at'] : null,
                                        ]);
                                    @endphp
                                    @if($metaParts)
                                        <div class="meta">{{ implode(' · ', $metaParts) }}</div>
                                    @endif
                                </div>
                                <div class="pm-chips">
                                    <span class="pm-chip"><span class="dot"></span>läuft</span>

                                    {{-- Deadline / Go-Live --}}
                                    @if($days !== null)
                                        @if($days >= 0)
                                            <span class="pm-chip neutral">
                                                @svg('heroicon-o-flag', 'w-3.5 h-3.5')
                                                {{ $current['planned_end'] }} · noch {{ $days }} {{ $days === 1 ? 'Tag' : 'Tage' }}
                                            </span>
                                        @else
                                            <span class="pm-chip warn">
                                                @svg('heroicon-o-flag', 'w-3.5 h-3.5')
                                                {{ $current['planned_end'] }} · {{ abs($days) }} {{ abs($days) === 1 ? 'Tag' : 'Tage' }} über Termin
                                            </span>
                                        @endif
                                    @endif

                                    {{-- Überfällige Aufgaben --}}
                                    @if($current['overdue_count'] > 0)
                                        <span class="pm-chip warn">
                                            @svg('heroicon-o-exclamation-triangle', 'w-3.5 h-3.5')
                                            {{ $current['overdue_count'] }} überfällig
                                        </span>
                                    @endif

                                    {{-- Health-Trend --}}
                                    @if($current['health_trend'] !== null)
                                        <span class="pm-chip trend" title="Interne Ampel-Entwicklung">
                                            <span class="tdot" style="background: {{ $hcColor }}"></span>
                                            @if($current['health_trend'] === 'up')
                                                @svg('heroicon-o-arrow-trending-up', 'w-3.5 h-3.5', ['style' => 'color:var(--good)'])
                                            @elseif($current['health_trend'] === 'down')
                                                @svg('heroicon-o-arrow-trending-down', 'w-3.5 h-3.5', ['style' => 'color:var(--warn)'])
                                            @else
                                                @svg('heroicon-o-minus-small', 'w-3.5 h-3.5')
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Metrik-Kacheln --}}
                            @php
                                $planned = $current['planned_minutes'];
                                $logged = $current['logged_minutes'];
                                $timePct = $planned > 0 ? min(100, round($logged / $planned * 100)) : null;
                                $over = $planned > 0 && $logged > $planned;
                                $dodPct = $current['dod_total'] > 0 ? round($current['dod_checked'] / $current['dod_total'] * 100) : null;
                            @endphp
                            {{-- Unteres Raster: links Aufgaben (scrollt), rechts Kennzahlen + Canvas (sticky) --}}
                            <div class="pm-split">
                                {{-- Offene Punkte --}}
                                <div class="pm-panel">
                                    <header>
                                        <span class="h">Offene Punkte</span>
                                        <span class="agg">
                                            {{ $current['open_task_count'] }} {{ $current['open_task_count'] === 1 ? 'Aufgabe' : 'Aufgaben' }}@if($current['dod_total'] > 0) · {{ $current['dod_checked'] }} / {{ $current['dod_total'] }} Kriterien erfüllt @endif
                                        </span>
                                    </header>
                                    <div class="pad">
                                        @forelse($current['tasks'] as $task)
                                            @php $donePill = $task['total'] > 0 && $task['checked'] === $task['total']; @endphp
                                            <div class="pm-task">
                                                <div class="row">
                                                    <button type="button" wire:click="completeTask({{ $task['id'] }})" class="pm-check" title="Aufgabe abhaken">
                                                        @svg('heroicon-o-check', 'w-3.5 h-3.5')
                                                    </button>
                                                    <span class="tt">{{ $task['title'] }}</span>
                                                    @if($task['total'] > 0)
                                                        <span class="pm-pill {{ $donePill ? 'done' : '' }}">{{ $task['checked'] }}/{{ $task['total'] }}</span>
                                                    @else
                                                        <span class="pm-pill">offen</span>
                                                    @endif
                                                </div>
                                                @if(! empty($task['open_items']))
                                                    <ul class="pm-dods">
                                                        @foreach($task['open_items'] as $item)
                                                            <li><span class="pm-box"></span><span>{{ $item }}</span></li>
                                                        @endforeach
                                                    </ul>
                                                @elseif($task['total'] === 0)
                                                    <div class="none">Noch keine Kriterien hinterlegt</div>
                                                @endif
                                            </div>
                                        @empty
                                            <div class="pm-empty-good">Alle Aufgaben erledigt 🎉</div>
                                        @endforelse
                                    </div>
                                </div>

                                {{-- Kennzahlen + Canvas-Essenz — läuft sticky mit --}}
                                <div class="pm-side">
                                    {{-- Zeit --}}
                                    <div class="pm-tile">
                                        <div class="pm-ring" style="--p: {{ $timePct ?? 0 }}; --c: {{ $over ? 'var(--warn)' : 'var(--accent)' }}">
                                            <span class="val">{{ $timePct !== null ? $timePct . '%' : '–' }}</span>
                                        </div>
                                        <div>
                                            <div class="label">Zeit</div>
                                            <div class="big">{{ $fmtHours($logged) }}<small> h investiert</small></div>
                                            <div class="note {{ $over ? 'warn' : '' }}">
                                                {{ $planned > 0 ? ($over ? 'über ' . $fmtHours($planned) . ' h geplant' : 'von ' . $fmtHours($planned) . ' h geplant') : 'keine Planung hinterlegt' }}
                                            </div>
                                        </div>
                                    </div>
                                    {{-- Fortschritt --}}
                                    <div class="pm-tile">
                                        <div class="pm-ring" style="--p: {{ $dodPct ?? 0 }}; --c: var(--good)">
                                            <span class="val">{{ $dodPct !== null ? $dodPct . '%' : '–' }}</span>
                                        </div>
                                        <div>
                                            <div class="label">Fortschritt</div>
                                            <div class="big">{{ $current['dod_checked'] }}<small> / {{ $current['dod_total'] }} Kriterien</small></div>
                                            <div class="note">{{ $current['dod_total'] > 0 ? 'Definition-of-Done erfüllt' : 'keine Kriterien definiert' }}</div>
                                        </div>
                                    </div>
                                    {{-- Story-Points (Velocity) — sonst Fallback offene Aufgaben --}}
                                    @if($current['sp_total'] > 0)
                                        @php $spPct = round($current['sp_done'] / $current['sp_total'] * 100); @endphp
                                        <div class="pm-tile">
                                            <div class="pm-ring" style="--p: {{ $spPct }}; --c: var(--accent)">
                                                <span class="val">{{ $spPct }}%</span>
                                            </div>
                                            <div>
                                                <div class="label">Story-Points</div>
                                                <div class="big">{{ $current['sp_done'] }}<small> / {{ $current['sp_total'] }} erledigt</small></div>
                                                <div class="note">Umfang nach Aufwand</div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="pm-tile plain">
                                            <div class="label">Offene Aufgaben</div>
                                            <div class="big">{{ $current['open_task_count'] }}</div>
                                            <div class="note">{{ $current['open_task_count'] === 1 ? 'Aufgabe in Arbeit' : 'Aufgaben in Arbeit' }}</div>
                                        </div>
                                    @endif

                                    @forelse($current['canvas'] as $block)
                                        <div class="pm-cvcard">
                                            <div class="lbl">{{ $block['label'] }}</div>
                                            @foreach($block['entries'] as $entry)
                                                <div class="pm-cventry">
                                                    @if($entry['title'])
                                                        <div class="et">{{ $entry['title'] }}</div>
                                                    @endif
                                                    @if($entry['content'])
                                                        <div class="ec">{{ $entry['content'] }}</div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @empty
                                        <div class="pm-cvcard">
                                            <div class="lbl">Canvas</div>
                                            <div class="pm-cventry"><div class="ec">Noch kein Canvas-Inhalt hinterlegt.</div></div>
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        @endif
                        </div>
                    </div>
                </div>

                {{-- Fußzeile / Navigation --}}
                <div class="pm-footer">
                    <button class="pm-navbtn" wire:click="prev" @disabled($index <= 0)>@svg('heroicon-o-chevron-left', 'w-4 h-4') Zurück</button>
                    <div class="pm-dots">
                        <button wire:click="goTo(0)" class="pm-dot {{ $index === 0 ? 'active' : '' }}" title="Überblick"></button>
                        @foreach($slides as $i => $s)
                            <button wire:click="goTo({{ $i + 1 }})" class="pm-dot {{ $index === $i + 1 ? 'active' : '' }}" title="{{ $s['name'] }}"></button>
                        @endforeach
                    </div>
                    <span class="pm-kbd"><b>←</b> <b>→</b> zum Blättern</span>
                    <button class="pm-navbtn primary" wire:click="next" @disabled($index >= $projectCount)>Weiter @svg('heroicon-o-chevron-right', 'w-4 h-4')</button>
                </div>
            @endif
        </div>
    @endif

</x-ui-page>
