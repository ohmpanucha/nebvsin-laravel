@extends('layouts.storefront')

@section('content')
    <section class="weekly-process-section reveal in-view">
        <div class="weekly-process-inner">
            <header class="weekly-process-header reveal in-view">
                <p class="weekly-process-kicker">{{ $copy['kicker'] }}</p>
                <h1 class="weekly-process-title">{{ $copy['title'] }}</h1>
                <div class="weekly-process-status" aria-label="{{ $processState['today_label'] }}">
                    <p class="weekly-process-status-label">{{ $processState['today_label'] }}</p>
                    <p class="weekly-process-status-date">{{ $processState['today'] }}</p>
                    <p class="weekly-process-status-summary">{{ $processState['summary'] }}</p>
                </div>
            </header>

            <div class="weekly-process-timeline reveal in-view" aria-label="{{ $copy['timeline_aria'] }}">
                <span class="weekly-process-timeline-line" aria-hidden="true"></span>
                <div class="weekly-process-timeline-steps">
                    @foreach ($copy['timeline_steps'] as $index => $step)
                        @php
                            $blockState = $processState['blocks'][$index]['state'] ?? 'default';
                        @endphp
                        <div class="weekly-process-timeline-step is-{{ $blockState }}">
                            <span class="weekly-process-timeline-dot" aria-hidden="true"></span>
                            <span class="weekly-process-timeline-text">{{ $step }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="weekly-process-grid">
                @foreach ($processState['blocks'] as $block)
                    <section class="weekly-process-block weekly-process-block--{{ $block['state'] }} reveal in-view">
                        @if (!empty($block['state_label']))
                            <p class="weekly-process-block-state">{{ $block['state_label'] }}</p>
                        @endif
                        <h2 class="weekly-process-block-title">{{ $block['title'] }}</h2>
                        <p class="weekly-process-block-detail">{{ $block['detail'] }}</p>
                    </section>
                @endforeach
            </div>

            <footer class="weekly-process-footer reveal in-view">
                <p class="weekly-process-footer-note">{{ $copy['footer'] }}</p>
                <div class="weekly-process-modal">
                    <h2 class="weekly-process-modal-title">{{ $copy['modal_title'] }}</h2>
                    <ul class="weekly-process-modal-list">
                        @foreach ($copy['modal_steps'] as $step)
                            <li>{{ $step }}</li>
                        @endforeach
                    </ul>
                </div>
            </footer>
        </div>
    </section>
@endsection
