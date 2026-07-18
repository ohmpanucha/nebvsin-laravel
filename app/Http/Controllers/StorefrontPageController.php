<?php

namespace App\Http\Controllers;

use App\Support\StorefrontLocale;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorefrontPageController extends Controller
{
    public function process(Request $request): View
    {
        $locale = StorefrontLocale::resolve($request);
        $copy = StorefrontLocale::copy('process', $locale);
        $processState = $this->resolveProcessState($locale, $copy);

        return view('storefront.process', [
            'locale' => $locale,
            'copy' => $copy,
            'processState' => $processState,
            'pageTitle' => $copy['seo_title'],
            'pageDescription' => $copy['seo_description'],
            'canonicalUrl' => route('storefront.process', ['lang' => $locale]),
            'ogImage' => asset('uploads/products/DROP01.png'),
        ]);
    }

    private function resolveProcessState(string $locale, array $copy): array
    {
        $today = CarbonImmutable::now('Asia/Bangkok');
        $phaseLabels = $copy['timeline_steps'] ?? ['CUT-OFF', 'PRODUCTION', 'SHIPPING'];
        $dayOfWeek = (int) $today->dayOfWeekIso;

        if ($dayOfWeek === 7) {
            $activeIndex = 0;
            $nextIndex = 1;
        } elseif ($dayOfWeek >= 1 && $dayOfWeek <= 3) {
            $activeIndex = 1;
            $nextIndex = 2;
        } elseif ($dayOfWeek >= 4 && $dayOfWeek <= 5) {
            $activeIndex = 2;
            $nextIndex = null;
        } else {
            $activeIndex = null;
            $nextIndex = 0;
        }

        $blocks = [];

        foreach ($copy['blocks'] ?? [] as $index => $block) {
            $state = 'default';

            if ($index === $activeIndex) {
                $state = 'active';
            } elseif ($index === $nextIndex) {
                $state = 'next';
            } elseif (
                ($activeIndex === 1 && $index === 0) ||
                ($activeIndex === 2 && in_array($index, [0, 1], true)) ||
                ($activeIndex === null && in_array($index, [1, 2], true))
            ) {
                $state = 'completed';
            }

            $stateLabel = null;

            if ($state === 'active') {
                $stateLabel = $copy['state_active'] ?? 'ACTIVE';
            } elseif ($state === 'next') {
                $stateLabel = $copy['state_next'] ?? 'NEXT';
            } elseif ($state === 'completed') {
                $stateLabel = $copy['state_completed'] ?? 'DONE';
            }

            $blocks[] = array_merge($block, [
                'state' => $state,
                'state_label' => $stateLabel,
            ]);
        }

        if ($dayOfWeek === 7) {
            $summary = $copy['summary_cutoff_live'] ?? 'Cut-off closes today at 23:59';
        } elseif ($dayOfWeek >= 1 && $dayOfWeek <= 3) {
            $summary = $copy['summary_production_live'] ?? 'Production in progress until Wednesday';
        } elseif ($dayOfWeek >= 4 && $dayOfWeek <= 5) {
            $summary = $copy['summary_shipping_live'] ?? 'Shipping in progress until Friday';
        } elseif ($dayOfWeek === 6) {
            $summary = $copy['summary_cutoff_tomorrow'] ?? 'Next cut-off tomorrow at 23:59';
        } else {
            $summaryKey = $activeIndex !== null ? 'summary_active' : 'summary_next';
            $summaryPhaseIndex = $activeIndex ?? $nextIndex ?? 0;
            $summary = str_replace(':phase', $phaseLabels[$summaryPhaseIndex] ?? '', $copy[$summaryKey] ?? '');
        }

        $dateFormat = $locale === 'th' ? 'l j F Y' : 'l, F j, Y';
        $formattedDate = $today->locale($locale === 'th' ? 'th' : 'en')->translatedFormat($dateFormat);

        return [
            'today_label' => $copy['today_label'] ?? 'Bangkok time',
            'today' => str_replace(':date', $formattedDate, $copy['summary_today'] ?? 'Today: :date'),
            'summary' => $summary,
            'blocks' => $blocks,
        ];
    }
}
