@php
    $isMarketsActive = request()->is('markets*') || (request()->is('wallet*') && !request()->has('tab') && !request()->has('guide'));
    $isCalendarActive = request()->is('calendar*') || request()->is('economic-calendar*');
    $isResearchActive = request()->is('research*') || request()->is('market-research*');
    $isNewsActive = request()->is('news*') || request()->is('market-news*');
@endphp

<div class="market-subnav-container border-b border-slate-800/80 mb-6 pb-0 overflow-x-auto text-center" style="scroll-behavior: smooth; -webkit-overflow-scrolling: touch; scrollbar-width: none;">
    <div class="flex items-center justify-center gap-6 sm:gap-10 min-w-max text-sm font-semibold mx-auto">
        <a href="{{ url('/wallet') }}" class="py-2.5 px-1 transition-all border-b-2 whitespace-nowrap {{ $isMarketsActive ? 'text-white font-bold border-purple-500' : 'text-slate-400 hover:text-white border-transparent' }}">
            Markets
        </a>
        <a href="{{ url('/economic-calendar') }}" class="py-2.5 px-1 transition-all border-b-2 whitespace-nowrap {{ $isCalendarActive ? 'text-white font-bold border-purple-500' : 'text-slate-400 hover:text-white border-transparent' }}">
            Economic calendar
        </a>
        <a href="{{ url('/research') }}" class="py-2.5 px-1 transition-all border-b-2 whitespace-nowrap {{ $isResearchActive ? 'text-white font-bold border-purple-500' : 'text-slate-400 hover:text-white border-transparent' }}">
            Research
        </a>
        <a href="{{ url('/news') }}" class="py-2.5 px-1 transition-all border-b-2 whitespace-nowrap {{ $isNewsActive ? 'text-white font-bold border-purple-500' : 'text-slate-400 hover:text-white border-transparent' }}">
            News
        </a>
    </div>
</div>
