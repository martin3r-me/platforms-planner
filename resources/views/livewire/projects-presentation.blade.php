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
        .pm-stage { max-width: 1760px; margin: 0 auto; padding: 30px 40px 40px; display: flex; flex-direction: column; gap: 22px; }
        .pm-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 24px; }
        .pm-head h1 { font-family: var(--serif); font-weight: 600; font-size: 40px; line-height: 1.05; margin: 0; letter-spacing: -.01em; text-wrap: balance; color: var(--ink); }
        .pm-head .meta { margin-top: 8px; font-size: 13.5px; color: var(--muted); }
        .pm-chip { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; padding: 5px 11px; border-radius: 999px; background: var(--good-soft); color: var(--good); white-space: nowrap; }
        .pm-chip .dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }

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
        .pm-pill { margin-left: auto; font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 999px; background: var(--ground); color: var(--muted); font-variant-numeric: tabular-nums; white-space: nowrap; }
        .pm-pill.done { background: var(--good-soft); color: var(--good); }
        .pm-dods { margin: 10px 0 2px; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 7px; }
        .pm-dods li { display: flex; align-items: flex-start; gap: 10px; font-size: 13.5px; color: var(--ink-soft); line-height: 1.4; }
        .pm-box { width: 15px; height: 15px; border-radius: 4px; border: 1.5px solid var(--line-strong); margin-top: 2px; flex-shrink: 0; }
        .pm-task .none { font-size: 12.5px; color: var(--muted); font-style: italic; margin-top: 4px; }
        .pm-empty-good { padding: 26px 20px; text-align: center; color: var(--good); font-size: 14px; font-weight: 600; }

        .pm-canvas { display: flex; flex-direction: column; gap: 14px; }
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

        @media (max-width: 1100px) { .pm-tiles { grid-template-columns: 1fr; } .pm-lower { grid-template-columns: 1fr; } }
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
                            <span>
                                <span class="en" style="display:block">{{ $e['name'] }}</span>
                                <span class="ec">{{ $e['count'] }} {{ $e['count'] === 1 ? 'laufendes Projekt' : 'laufende Projekte' }}</span>
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
            $total = count($slides);
            $current = $slides[$index] ?? null;
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
                @if($total > 0)
                    <div class="pm-counter">Projekt {{ $index + 1 }} / {{ $total }}</div>
                @endif
            </div>

            @if(! $current)
                <div class="pm-center">
                    <div>
                        @svg('heroicon-o-folder-open', 'w-8 h-8', ['style' => 'color:var(--muted)'])
                        <h3 style="margin:10px 0 4px;font-size:16px">Keine laufenden Projekte</h3>
                        <p style="margin:0;color:var(--muted);font-size:13.5px">Für dieses Engagement gibt es gerade nichts durchzusprechen.</p>
                    </div>
                </div>
            @else
                <div class="pm-body">
                    {{-- Projekt-Navigator --}}
                    <aside class="pm-rail">
                        <h2>Projekte</h2>
                        <div class="pm-navlist">
                            @foreach($slides as $i => $s)
                                @php $pct = $s['dod_total'] > 0 ? round($s['dod_checked'] / $s['dod_total'] * 100) : 0; @endphp
                                <button type="button" wire:click="goTo({{ $i }})" class="pm-navitem {{ $i === $index ? 'active' : '' }}">
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
                    <div class="pm-stagewrap" wire:key="stage-{{ $current['id'] }}">
                        <div class="pm-stage">
                            {{-- Kopf --}}
                            <div class="pm-head">
                                <div>
                                    <h1>{{ $current['name'] }}</h1>
                                    @if($current['owner_name'])
                                        <div class="meta">Verantwortlich · {{ $current['owner_name'] }}</div>
                                    @endif
                                </div>
                                <span class="pm-chip"><span class="dot"></span>läuft</span>
                            </div>

                            {{-- Metrik-Kacheln --}}
                            @php
                                $planned = $current['planned_minutes'];
                                $logged = $current['logged_minutes'];
                                $timePct = $planned > 0 ? min(100, round($logged / $planned * 100)) : null;
                                $over = $planned > 0 && $logged > $planned;
                                $dodPct = $current['dod_total'] > 0 ? round($current['dod_checked'] / $current['dod_total'] * 100) : null;
                            @endphp
                            <div class="pm-tiles">
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
                                {{-- Offene Aufgaben --}}
                                <div class="pm-tile plain">
                                    <div class="label">Offene Aufgaben</div>
                                    <div class="big">{{ $current['open_task_count'] }}</div>
                                    <div class="note">{{ $current['open_task_count'] === 1 ? 'Aufgabe in Arbeit' : 'Aufgaben in Arbeit' }}</div>
                                </div>
                            </div>

                            {{-- Unteres Raster --}}
                            <div class="pm-lower">
                                {{-- Offene Punkte --}}
                                <div class="pm-panel">
                                    <header>
                                        <span class="h">Offene Punkte</span>
                                        @if($current['dod_total'] > 0)
                                            <span class="agg">{{ $current['dod_checked'] }} / {{ $current['dod_total'] }} Kriterien erfüllt</span>
                                        @endif
                                    </header>
                                    <div class="pad">
                                        @forelse($current['tasks'] as $task)
                                            @php $donePill = $task['total'] > 0 && $task['checked'] === $task['total']; @endphp
                                            <div class="pm-task">
                                                <div class="row">
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

                                {{-- Canvas-Essenz --}}
                                <div class="pm-canvas">
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
                        </div>
                    </div>
                </div>

                {{-- Fußzeile / Navigation --}}
                <div class="pm-footer">
                    <button class="pm-navbtn" wire:click="prev" @disabled($index <= 0)>@svg('heroicon-o-chevron-left', 'w-4 h-4') Zurück</button>
                    <div class="pm-dots">
                        @foreach($slides as $i => $s)
                            <button wire:click="goTo({{ $i }})" class="pm-dot {{ $i === $index ? 'active' : '' }}" title="{{ $s['name'] }}"></button>
                        @endforeach
                    </div>
                    <span class="pm-kbd"><b>←</b> <b>→</b> zum Blättern</span>
                    <button class="pm-navbtn primary" wire:click="next" @disabled($index >= $total - 1)>Weiter @svg('heroicon-o-chevron-right', 'w-4 h-4')</button>
                </div>
            @endif
        </div>
    @endif

</x-ui-page>
