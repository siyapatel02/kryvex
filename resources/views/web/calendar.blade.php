@extends('layouts.app')

@section('title', 'Economic Calendar & Market Events - Kryvex')

@push('styles')
<style>
    /* Ensure body & html scroll smoothly with mouse wheel */
    html, body {
        overflow-x: hidden;
        overflow-y: auto !important;
        height: auto !important;
        scroll-behavior: smooth;
    }

    /* Custom scrollbar styling for calendar table container */
    .cal-scrollbar::-webkit-scrollbar {
        height: 6px;
        width: 6px;
    }
    .cal-scrollbar::-webkit-scrollbar-track {
        background: rgba(15, 23, 42, 0.6);
    }
    .cal-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(139, 92, 246, 0.3);
        border-radius: 10px;
    }
    .cal-scrollbar::-webkit-scrollbar-thumb:hover {
        background: rgba(139, 92, 246, 0.6);
    }

    /* Remove native focus outline rings on date preset pills */
    .cal-date-pill:focus,
    .cal-impact-pill:focus,
    .cal-date-pill:active,
    .cal-impact-pill:active {
        outline: none !important;
        box-shadow: none !important;
    }
</style>
@endpush

@push('scripts')
    <script src="https://unpkg.com/lenis@1.1.18/dist/lenis.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof Lenis !== 'undefined') {
                const lenis = new Lenis({
                    duration: 1.2,
                    easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
                    smoothWheel: true,
                    wheelMultiplier: 1.0
                });
                function raf(time) {
                    lenis.raf(time);
                    requestAnimationFrame(raf);
                }
                requestAnimationFrame(raf);
            }
        });
    </script>
@endpush

@section('content')
<div class="calendar-page text-slate-100 min-h-screen pb-20 relative bg-[#060911]" style="font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;">
    
    <!-- HERO HEADER SECTION -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 pb-4">
        
        @include('partials.market-subnav')

        <!-- Main Hero Title -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-6 border-b border-slate-800/40">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-400 text-xs font-semibold mb-2">
                    <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                    <span class="w-2 h-2 rounded-full bg-amber-400 -ml-4"></span>
                    <span>Live Macroeconomic Meetings</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-bold text-slate-100 tracking-tight mb-2">
                    Economic Calendar
                </h1>
                <p class="text-slate-400 text-sm max-w-3xl leading-relaxed">
                    Track high-impact global macroeconomic releases, interest rate decisions, FOMC minutes, CPI inflation numbers, non-farm payrolls (NFP), and central bank speeches in real-time.
                </p>
            </div>
        </div>

        <!-- STAT SUMMARY CARDS (Single line 4-column layout with clean vector icons) -->
        <div class="grid grid-cols-4 gap-4 mt-6">
            <div class="bg-[#0b0f19]/80 border border-slate-800/60 rounded-2xl p-4 flex items-center justify-between shadow-sm backdrop-blur-md">
                <div>
                    <span class="text-xs font-medium text-slate-400 block mb-1">Total Meetings</span>
                    <span class="text-xl sm:text-2xl font-bold text-slate-200" id="statTotalEvents">0</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-400 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
            </div>

            <div class="bg-[#0b0f19]/80 border border-slate-800/60 rounded-2xl p-4 flex items-center justify-between shadow-sm backdrop-blur-md">
                <div>
                    <span class="text-xs font-medium text-slate-400 block mb-1">High Impact</span>
                    <span class="text-xl sm:text-2xl font-bold text-red-400" id="statHighEvents">0</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
            </div>

            <div class="bg-[#0b0f19]/80 border border-slate-800/60 rounded-2xl p-4 flex items-center justify-between shadow-sm backdrop-blur-md">
                <div>
                    <span class="text-xs font-medium text-slate-400 block mb-1">Medium Impact</span>
                    <span class="text-xl sm:text-2xl font-bold text-amber-400" id="statMediumEvents">0</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>

            <div class="bg-[#0b0f19]/80 border border-slate-800/60 rounded-2xl p-4 flex items-center justify-between shadow-sm backdrop-blur-md">
                <div>
                    <span class="text-xs font-medium text-slate-400 block mb-1">Low Impact</span>
                    <span class="text-xl sm:text-2xl font-bold text-slate-400" id="statLowEvents">0</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-slate-500/10 border border-slate-500/20 text-slate-400 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- FILTER & SEARCH CONTROL BAR -->
        <div class="bg-[#0b0f19]/90 border border-slate-800/60 rounded-2xl p-5 mt-6 shadow-md backdrop-blur-md">
            
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                
                <!-- Date Preset Filter Pills -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1 lg:pb-0" id="dateFilterPills">
                    <button type="button" data-date="all" onclick="setCalendarDateFilter('all')" class="cal-date-pill px-4 py-2 rounded-xl text-xs font-semibold transition-all bg-purple-600/30 text-purple-300 border border-purple-500/40 outline-none focus:outline-none select-none">
                        All Dates
                    </button>
                    <button type="button" data-date="today" onclick="setCalendarDateFilter('today')" class="cal-date-pill px-4 py-2 rounded-xl text-xs font-medium transition-all bg-slate-800/60 text-slate-300 hover:text-white border border-slate-700/40 outline-none focus:outline-none select-none">
                        Today
                    </button>
                    <button type="button" data-date="tomorrow" onclick="setCalendarDateFilter('tomorrow')" class="cal-date-pill px-4 py-2 rounded-xl text-xs font-medium transition-all bg-slate-800/60 text-slate-300 hover:text-white border border-slate-700/40 outline-none focus:outline-none select-none">
                        Tomorrow
                    </button>
                    <button type="button" data-date="week" onclick="setCalendarDateFilter('week')" class="cal-date-pill px-4 py-2 rounded-xl text-xs font-medium transition-all bg-slate-800/60 text-slate-300 hover:text-white border border-slate-700/40 outline-none focus:outline-none select-none">
                        This Week
                    </button>
                </div>

                <!-- Custom Date Picker + Country Select + Search Input -->
                <div class="flex flex-wrap items-center gap-3">
                    
                    <!-- Specific Date Picker Input -->
                    <div class="flex items-center gap-2 bg-slate-900/80 border border-slate-700/50 rounded-xl px-3.5 py-1.5 focus-within:border-purple-500/60 transition-colors shadow-inner">
                        <svg class="w-4 h-4 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <input type="date" id="customDatePicker" onchange="onCustomDatePicked(this.value)" class="bg-transparent text-slate-200 text-xs font-medium focus:outline-none outline-none cursor-pointer" title="Filter by specific date">
                    </div>

                    <!-- Currency / Country Selector -->
                    <select id="countrySelectFilter" onchange="onCountryFilterChange(this.value)" class="bg-slate-900/80 border border-slate-700/50 text-slate-200 text-xs font-medium rounded-xl px-3.5 py-2 focus:outline-none focus:border-purple-500/60 transition-colors shadow-inner cursor-pointer">
                        <option value="all">🌐 All Countries</option>
                        <option value="us">🇺🇸 USD (United States)</option>
                        <option value="gb">🇬🇧 GBP (United Kingdom)</option>
                        <option value="eu">🇪🇺 EUR (Euro Area)</option>
                        <option value="ca">🇨🇦 CAD (Canada)</option>
                        <option value="jp">🇯🇵 JPY (Japan)</option>
                        <option value="au">🇦🇺 AUD (Australia)</option>
                        <option value="ch">🇨🇭 CHF (Switzerland)</option>
                        <option value="nz">🇳🇿 NZD (New Zealand)</option>
                        <option value="cn">🇨🇳 CNY (China)</option>
                    </select>

                    <!-- Search Input Box (Clean SVG Icon + Explicit padding-left inline style to avoid text overlap) -->
                    <div class="relative flex-1 sm:flex-initial">
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" id="calendarSearchInput" oninput="onCalendarSearchInput(this.value)" placeholder="Search meeting (e.g. FOMC, CPI)..." style="padding-left: 36px !important;" class="w-full sm:w-64 bg-slate-900/80 border border-slate-700/50 text-slate-200 text-xs font-medium rounded-xl py-2 focus:outline-none focus:border-purple-500/60 transition-colors shadow-inner">
                        <button type="button" id="clearSearchBtn" onclick="clearCalendarSearch()" class="hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white text-xs font-bold px-1">✕</button>
                    </div>

                    <!-- Reset All Filters Button -->
                    <button type="button" id="resetFiltersBtn" onclick="resetAllCalendarFilters()" class="hidden px-3.5 py-2 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-medium transition-all border border-slate-700/50 outline-none focus:outline-none">
                        Clear Filters
                    </button>

                </div>

            </div>

            <!-- Impact Pills Filter Toolbar -->
            <div class="flex items-center gap-2 mt-4 pt-3 border-t border-slate-800/40 overflow-x-auto">
                <span class="text-xs font-medium text-slate-400 mr-2 flex-shrink-0">Impact Level:</span>
                <button type="button" data-impact="all" onclick="setCalendarImpactFilter('all')" class="cal-impact-pill px-3.5 py-1.5 rounded-full text-xs font-medium transition-all bg-white/10 text-slate-200 border border-white/20 outline-none focus:outline-none">
                    All
                </button>
                <button type="button" data-impact="high" onclick="setCalendarImpactFilter('high')" class="cal-impact-pill px-3.5 py-1.5 rounded-full text-xs font-medium transition-all bg-red-500/10 text-red-400 hover:bg-red-500/20 border border-red-500/20 outline-none focus:outline-none">
                    ▼ High Impact
                </button>
                <button type="button" data-impact="medium" onclick="setCalendarImpactFilter('medium')" class="cal-impact-pill px-3.5 py-1.5 rounded-full text-xs font-medium transition-all bg-amber-500/10 text-amber-400 hover:bg-amber-500/20 border border-amber-500/20 outline-none focus:outline-none">
                    ▼ Medium Impact
                </button>
                <button type="button" data-impact="low" onclick="setCalendarImpactFilter('low')" class="cal-impact-pill px-3.5 py-1.5 rounded-full text-xs font-medium transition-all bg-slate-500/10 text-slate-400 hover:bg-slate-500/20 border border-slate-500/20 outline-none focus:outline-none">
                    ▼ Low Impact
                </button>
            </div>

        </div>

        <!-- EVENTS TABLE (DESKTOP) + CARDS (MOBILE) SECTION -->
        <div class="bg-[#0b0f19]/90 border border-slate-800/60 rounded-2xl overflow-hidden mt-6 shadow-md">
            
            <!-- DESKTOP TABLE VIEW (5 COLUMNS - Impact Level column removed) -->
            <div class="hidden md:block overflow-x-auto cal-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-900/60 text-slate-400 text-xs font-semibold uppercase border-b border-slate-800/40">
                            <th class="py-3.5 px-5">Date & Time</th>
                            <th class="py-3.5 px-4">Country</th>
                            <th class="py-3.5 px-5">Event Title</th>
                            <th class="py-3.5 px-4 text-center">Impact</th>
                            <th class="py-3.5 px-5 text-right">Action / Source</th>
                        </tr>
                    </thead>
                    <tbody id="calendarEventsTbody" class="divide-y divide-slate-800/40 text-sm font-normal text-slate-200">
                        <tr>
                            <td colspan="5" class="text-center py-16 text-slate-400 text-xs">
                                <div class="inline-block w-7 h-7 border-2 border-purple-500/30 border-t-purple-500 rounded-full animate-spin mb-3"></div>
                                <p class="font-medium text-slate-300">Syncing live economic calendar meetings...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- MOBILE CARDS VIEW -->
            <div class="block md:hidden divide-y divide-slate-800/40" id="calendarEventsMobileCards">
                <div class="p-8 text-center text-slate-400 text-xs">
                    <div class="inline-block w-6 h-6 border-2 border-purple-500/30 border-t-purple-500 rounded-full animate-spin mb-2"></div>
                    <p>Loading events...</p>
                </div>
            </div>

        </div>

    </div>

</div>

@push('scripts')
<script>
    (function() {
        let allCalendarEvents = [];
        let currentDateFilter = 'all';
        let currentImpactFilter = 'all';
        let currentCountryFilter = 'all';
        let currentSearchQuery = '';
        let customDatePicked = '';

        function getCountryCode(ev) {
            if (ev.country) return String(ev.country).toLowerCase();
            if (ev.currency) {
                const cur = String(ev.currency).toLowerCase();
                if (cur === 'usd') return 'us';
                if (cur === 'gbp') return 'gb';
                if (cur === 'eur') return 'eu';
                if (cur === 'cad') return 'ca';
                if (cur === 'jpy') return 'jp';
                if (cur === 'aud') return 'au';
                if (cur === 'chf') return 'ch';
                if (cur === 'nzd') return 'nz';
                if (cur === 'cny') return 'cn';
            }

            const flag = String(ev.flag || '');
            if (flag.includes('🇺🇸') || flag.toLowerCase().includes('us')) return 'us';
            if (flag.includes('🇬🇧') || flag.toLowerCase().includes('gb') || flag.toLowerCase().includes('uk')) return 'gb';
            if (flag.includes('🇪🇺') || flag.toLowerCase().includes('eu')) return 'eu';
            if (flag.includes('🇨🇦') || flag.toLowerCase().includes('ca')) return 'ca';
            if (flag.includes('🇯🇵') || flag.toLowerCase().includes('jp')) return 'jp';
            if (flag.includes('🇦🇺') || flag.toLowerCase().includes('au')) return 'au';
            if (flag.includes('🇨🇭') || flag.toLowerCase().includes('ch')) return 'ch';
            if (flag.includes('🇳ℤ') || flag.toLowerCase().includes('nz')) return 'nz';
            if (flag.includes('🇨🇳') || flag.toLowerCase().includes('cn')) return 'cn';

            const title = String(ev.title || '').toLowerCase();
            if (title.includes('fomc') || title.includes('cpi') || title.includes('nfp') || title.includes('fed') || title.includes('unemployment')) return 'us';
            if (title.includes('boe') || title.includes('uk') || title.includes('britain')) return 'gb';
            if (title.includes('ecb') || title.includes('euro') || title.includes('germany')) return 'eu';
            if (title.includes('boj') || title.includes('japan') || title.includes('yen')) return 'jp';
            if (title.includes('rba') || title.includes('australia')) return 'au';
            if (title.includes('ivey') || title.includes('canada')) return 'ca';

            return 'us';
        }

        function renderCountryCircleBadge(ev) {
            const code = getCountryCode(ev);
            const codeUpper = code.toUpperCase();
            return `
                <span style="width: 26px; height: 26px; border-radius: 50%; overflow: hidden; flex-shrink: 0; background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.25); display: inline-flex; align-items: center; justify-content: center;">
                    <img src="https://flagcdn.com/w40/${code}.png" alt="${codeUpper}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
                    <span style="display: none; font-size: 0.65rem; font-weight: 800; color: #ffffff; text-transform: uppercase;">${codeUpper}</span>
                </span>
            `;
        }

        function renderTriangles(impact) {
            const imp = (impact || 'medium').toLowerCase();
            if (imp === 'high') {
                return `<span style="letter-spacing: -2px; font-size: 0.85rem;"><span style="color: #ef4444;">▼</span><span style="color: #ef4444;">▼</span><span style="color: #ef4444;">▼</span></span>`;
            } else if (imp === 'medium') {
                return `<span style="letter-spacing: -2px; font-size: 0.85rem;"><span style="color: #f59e0b;">▼</span><span style="color: #f59e0b;">▼</span><span style="color: rgba(255, 255, 255, 0.2);">▼</span></span>`;
            } else {
                return `<span style="letter-spacing: -2px; font-size: 0.85rem;"><span style="color: #9ca3af;">▼</span><span style="color: rgba(255, 255, 255, 0.2);">▼</span><span style="color: rgba(255, 255, 255, 0.2);">▼</span></span>`;
            }
        }

        function formatEventDate(ev) {
            if (ev.timestamp) {
                const d = new Date(ev.timestamp * 1000);
                const month = d.toLocaleString('en-US', { month: 'short' });
                const day = d.getDate();
                const hours = String(d.getHours()).padStart(2, '0');
                const mins = String(d.getMinutes()).padStart(2, '0');
                return { dateStr: `${month} ${day}`, timeStr: `${hours}:${mins}` };
            }
            return { dateStr: ev.date || 'Today', timeStr: ev.time || '12:00' };
        }

        function updateStats(events) {
            const total = events.length;
            const high = events.filter(e => (e.impact || '').toLowerCase() === 'high').length;
            const med = events.filter(e => (e.impact || '').toLowerCase() === 'medium').length;
            const low = events.filter(e => (e.impact || '').toLowerCase() === 'low').length;

            document.getElementById('statTotalEvents').textContent = total;
            document.getElementById('statHighEvents').textContent = high;
            document.getElementById('statMediumEvents').textContent = med;
            document.getElementById('statLowEvents').textContent = low;

            const isFiltered = (currentDateFilter !== 'all' || currentImpactFilter !== 'all' || currentCountryFilter !== 'all' || currentSearchQuery !== '' || customDatePicked !== '');
            const resetBtn = document.getElementById('resetFiltersBtn');
            if (resetBtn) {
                if (isFiltered) resetBtn.classList.remove('hidden');
                else resetBtn.classList.add('hidden');
            }
        }

        function filterEvents() {
            const now = new Date();
            const todayStr = now.toISOString().split('T')[0];
            
            const tomorrow = new Date(now);
            tomorrow.setDate(tomorrow.getDate() + 1);
            const tomorrowStr = tomorrow.toISOString().split('T')[0];

            const weekLater = new Date(now);
            weekLater.setDate(weekLater.getDate() + 7);

            let filtered = allCalendarEvents.filter(ev => {
                // 1. Impact filter
                if (currentImpactFilter !== 'all') {
                    if ((ev.impact || '').toLowerCase() !== currentImpactFilter) return false;
                }

                // 2. Country filter
                if (currentCountryFilter !== 'all') {
                    const cCode = getCountryCode(ev);
                    if (cCode !== currentCountryFilter) return false;
                }

                // 3. Search query filter
                if (currentSearchQuery) {
                    const title = (ev.title || '').toLowerCase();
                    if (!title.includes(currentSearchQuery)) return false;
                }

                // 4. Date filter
                let evDateObj = null;
                if (ev.timestamp) {
                    evDateObj = new Date(ev.timestamp * 1000);
                }

                if (customDatePicked && evDateObj) {
                    const evYmd = evDateObj.toISOString().split('T')[0];
                    if (evYmd !== customDatePicked) return false;
                } else if (currentDateFilter === 'today' && evDateObj) {
                    const evYmd = evDateObj.toISOString().split('T')[0];
                    if (evYmd !== todayStr) return false;
                } else if (currentDateFilter === 'tomorrow' && evDateObj) {
                    const evYmd = evDateObj.toISOString().split('T')[0];
                    if (evYmd !== tomorrowStr) return false;
                } else if (currentDateFilter === 'week' && evDateObj) {
                    if (evDateObj < now || evDateObj > weekLater) return false;
                }

                return true;
            });

            renderTableAndMobile(filtered);
        }

        function renderTableAndMobile(events) {
            const tbody = document.getElementById('calendarEventsTbody');
            const mobileContainer = document.getElementById('calendarEventsMobileCards');

            updateStats(events);

            if (!events || events.length === 0) {
                const emptyHtml = `
                    <div class="text-center py-16 px-4 text-slate-400">
                        <span class="text-4xl block mb-3">🔎</span>
                        <h4 class="font-extrabold text-base text-slate-200">No economic meetings found</h4>
                        <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">Try clearing your date picker, changing the impact level, or clearing search keywords.</p>
                        <button type="button" onclick="resetAllCalendarFilters()" class="mt-4 px-4 py-2 rounded-xl bg-purple-600 text-white font-bold text-xs shadow-md hover:bg-purple-500 transition-all">Reset All Filters</button>
                    </div>
                `;
                if (tbody) {
                    tbody.innerHTML = `<tr><td colspan="5">${emptyHtml}</td></tr>`;
                }
                if (mobileContainer) {
                    mobileContainer.innerHTML = emptyHtml;
                }
                return;
            }

            // Render Desktop Table Rows (5 Columns)
            if (tbody) {
                tbody.innerHTML = events.map(ev => {
                    const { dateStr, timeStr } = formatEventDate(ev);
                    const codeUpper = getCountryCode(ev).toUpperCase();
                    const impLower = (ev.impact || 'medium').toLowerCase();
                    const textColor = impLower === 'high' ? '#ef4444' : (impLower === 'low' ? '#9ca3af' : '#f59e0b');
                    const impactText = (ev.impact || 'Medium');
                    
                    return `
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="py-3.5 px-5 whitespace-nowrap">
                                <div class="text-slate-200 font-semibold text-xs md:text-sm">${dateStr}</div>
                                <div class="text-[11px] text-slate-400 font-normal">${timeStr}</div>
                            </td>
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <div class="flex items-center gap-2.5">
                                    ${renderCountryCircleBadge(ev)}
                                    <span class="font-semibold text-xs text-slate-300 uppercase">${codeUpper}</span>
                                </div>
                            </td>
                            <td class="py-3.5 px-5">
                                <div class="font-medium text-slate-200 text-xs md:text-sm max-w-md">${ev.title}</div>
                            </td>
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                ${renderTriangles(ev.impact)}
                            </td>
                            <td class="py-3.5 px-5 text-right whitespace-nowrap">
                                <a href="${ev.link || 'https://www.forexfactory.com/calendar'}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800/60 hover:bg-slate-700/80 text-xs font-semibold text-purple-300 transition-all border border-slate-700/40 shadow-sm">
                                    <span>Forex Factory</span>
                                    <span>&rarr;</span>
                                </a>
                            </td>
                        </tr>
                    `;
                }).join('');
            }

            // Render Mobile Cards
            if (mobileContainer) {
                mobileContainer.innerHTML = events.map(ev => {
                    const { dateStr, timeStr } = formatEventDate(ev);
                    const codeUpper = getCountryCode(ev).toUpperCase();
                    
                    return `
                        <div class="p-4 flex flex-col gap-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    ${renderCountryCircleBadge(ev)}
                                    <span class="font-semibold text-xs text-slate-200 uppercase">${codeUpper}</span>
                                    <span class="text-xs font-normal text-slate-400">&bull; ${dateStr} at ${timeStr}</span>
                                </div>
                                <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg bg-slate-900/80 border border-slate-800/60">
                                    ${renderTriangles(ev.impact)}
                                </div>
                            </div>
                            
                            <div class="font-medium text-slate-200 text-sm">
                                ${ev.title}
                            </div>

                            <div class="flex items-center justify-end pt-1 border-t border-slate-800/40">
                                <a href="${ev.link || 'https://www.forexfactory.com/calendar'}" target="_blank" rel="noopener noreferrer" class="text-xs font-medium text-purple-400 hover:text-purple-300">
                                    View Source &rarr;
                                </a>
                            </div>
                        </div>
                    `;
                }).join('');
            }
        }

        window.setCalendarDateFilter = function(datePreset) {
            currentDateFilter = datePreset;
            customDatePicked = '';
            document.getElementById('customDatePicker').value = '';

            document.querySelectorAll('#dateFilterPills button').forEach(btn => {
                if (btn.getAttribute('data-date') === datePreset) {
                    btn.className = 'cal-date-pill px-4 py-2 rounded-xl text-xs font-extrabold transition-all bg-purple-600 text-white border border-purple-500/50 outline-none focus:outline-none select-none';
                } else {
                    btn.className = 'cal-date-pill px-4 py-2 rounded-xl text-xs font-bold transition-all bg-slate-800/90 text-slate-300 hover:text-white border border-slate-700/60 outline-none focus:outline-none select-none';
                }
            });

            filterEvents();
        };

        window.onCustomDatePicked = function(val) {
            customDatePicked = val;
            currentDateFilter = 'custom';
            document.querySelectorAll('#dateFilterPills button').forEach(btn => {
                btn.className = 'cal-date-pill px-4 py-2 rounded-xl text-xs font-bold transition-all bg-slate-800/90 text-slate-300 hover:text-white border border-slate-700/60 outline-none focus:outline-none select-none';
            });
            filterEvents();
        };

        window.setCalendarImpactFilter = function(impactVal) {
            currentImpactFilter = impactVal;
            document.querySelectorAll('.cal-impact-pill').forEach(btn => {
                if (btn.getAttribute('data-impact') === impactVal) {
                    btn.className = 'cal-impact-pill px-3.5 py-1.5 rounded-full text-xs font-bold transition-all bg-white/20 text-white border border-white/30 outline-none focus:outline-none';
                } else {
                    const imp = btn.getAttribute('data-impact');
                    if (imp === 'high') {
                        btn.className = 'cal-impact-pill px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all bg-red-500/10 text-red-400 hover:bg-red-500/20 border border-red-500/30 outline-none focus:outline-none';
                    } else if (imp === 'medium') {
                        btn.className = 'cal-impact-pill px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all bg-amber-500/10 text-amber-400 hover:bg-amber-500/20 border border-amber-500/30 outline-none focus:outline-none';
                    } else if (imp === 'low') {
                        btn.className = 'cal-impact-pill px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all bg-slate-500/10 text-slate-400 hover:bg-slate-500/20 border border-slate-500/30 outline-none focus:outline-none';
                    } else {
                        btn.className = 'cal-impact-pill px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all bg-slate-800 text-slate-300 border border-slate-700 outline-none focus:outline-none';
                    }
                }
            });
            filterEvents();
        };

        window.onCountryFilterChange = function(val) {
            currentCountryFilter = val;
            filterEvents();
        };

        window.onCalendarSearchInput = function(val) {
            currentSearchQuery = (val || '').trim().toLowerCase();
            const clearBtn = document.getElementById('clearSearchBtn');
            if (clearBtn) {
                if (currentSearchQuery) clearBtn.classList.remove('hidden');
                else clearBtn.classList.add('hidden');
            }
            filterEvents();
        };

        window.clearCalendarSearch = function() {
            document.getElementById('calendarSearchInput').value = '';
            currentSearchQuery = '';
            document.getElementById('clearSearchBtn').classList.add('hidden');
            filterEvents();
        };

        window.resetAllCalendarFilters = function() {
            currentDateFilter = 'all';
            currentImpactFilter = 'all';
            currentCountryFilter = 'all';
            currentSearchQuery = '';
            customDatePicked = '';

            document.getElementById('customDatePicker').value = '';
            document.getElementById('countrySelectFilter').value = 'all';
            document.getElementById('calendarSearchInput').value = '';
            const clearBtn = document.getElementById('clearSearchBtn');
            if (clearBtn) clearBtn.classList.add('hidden');

            setCalendarDateFilter('all');
            setCalendarImpactFilter('all');
        };

        async function initCalendarPage() {
            try {
                const res = await fetch('/api/markets/upcoming-events');
                if (res.ok) {
                    const data = await res.json();
                    if (data.success && data.data && data.data.length > 0) {
                        allCalendarEvents = data.data;
                    }
                }
            } catch (err) {
                console.error('Error fetching live economic calendar events:', err);
            }

            if (!allCalendarEvents || allCalendarEvents.length === 0) {
                const nowSec = Math.floor(Date.now() / 1000);
                allCalendarEvents = [
                    { timestamp: nowSec + 3600 * 2, date: 'Oct 7', time: '23:30', flag: '🇺🇸', currency: 'USD', title: 'FOMC Meeting Minutes', impact: 'High', link: 'https://www.forexfactory.com/calendar' },
                    { timestamp: nowSec + 3600 * 6, date: 'Oct 7', time: '17:45', flag: '🇬🇧', currency: 'GBP', title: 'BOE Gov Bailey Speaks', impact: 'High', link: 'https://www.forexfactory.com/calendar' },
                    { timestamp: nowSec + Math.floor(86400 * 1.2), date: 'Oct 8', time: '13:30', flag: '🇺🇸', currency: 'USD', title: 'Core CPI (MoM)', impact: 'High', link: 'https://www.forexfactory.com/calendar' },
                    { timestamp: nowSec + Math.floor(86400 * 1.8), date: 'Oct 8', time: '17:00', flag: '🇪🇺', currency: 'EUR', title: 'ECB Press Conference', impact: 'High', link: 'https://www.forexfactory.com/calendar' },
                    { timestamp: nowSec + Math.floor(86400 * 2.5), date: 'Oct 9', time: '18:30', flag: '🇺🇸', currency: 'USD', title: 'Non-Farm Employment Change', impact: 'High', link: 'https://www.forexfactory.com/calendar' },
                    { timestamp: nowSec + 3600 * 4, date: 'Oct 8', time: '19:30', flag: '🇨🇦', currency: 'CAD', title: 'Ivey PMI (Sep)', impact: 'Medium', link: 'https://www.forexfactory.com/calendar' },
                    { timestamp: nowSec + 3600 * 10, date: 'Oct 8', time: '14:00', flag: '🇺🇸', currency: 'USD', title: 'FOMC Member Waller Speaks', impact: 'Medium', link: 'https://www.forexfactory.com/calendar' },
                    { timestamp: nowSec + Math.floor(86400 * 1.1), date: 'Oct 8', time: '15:00', flag: '🇯🇵', currency: 'JPY', title: 'BOJ Summary of Opinions', impact: 'Medium', link: 'https://www.forexfactory.com/calendar' },
                    { timestamp: nowSec + Math.floor(86400 * 1.6), date: 'Oct 9', time: '16:30', flag: '🇦🇺', currency: 'AUD', title: 'RBA Rate Statement', impact: 'Medium', link: 'https://www.forexfactory.com/calendar' },
                    { timestamp: nowSec + Math.floor(86400 * 2.2), date: 'Oct 9', time: '19:00', flag: '🇬🇧', currency: 'GBP', title: 'GDP (MoM)', impact: 'Medium', link: 'https://www.forexfactory.com/calendar' },
                    { timestamp: nowSec + 3600 * 8, date: 'Oct 9', time: '18:00', flag: '🇺🇸', currency: 'USD', title: 'Unemployment Claims', impact: 'Low', link: 'https://www.forexfactory.com/calendar' },
                    { timestamp: nowSec + 3600 * 16, date: 'Oct 9', time: '19:30', flag: '🇺🇸', currency: 'USD', title: 'Crude Oil Inventories', impact: 'Low', link: 'https://www.forexfactory.com/calendar' },
                    { timestamp: nowSec + Math.floor(86400 * 3.1), date: 'Oct 10', time: '14:30', flag: '🇪🇺', currency: 'EUR', title: 'German ZEW Economic Sentiment', impact: 'Medium', link: 'https://www.forexfactory.com/calendar' },
                    { timestamp: nowSec + Math.floor(86400 * 3.5), date: 'Oct 10', time: '17:30', flag: '🇨🇭', currency: 'CHF', title: 'SNB Chairman Speech', impact: 'Medium', link: 'https://www.forexfactory.com/calendar' },
                    { timestamp: nowSec + Math.floor(86400 * 4.0), date: 'Oct 11', time: '08:30', flag: '🇨🇳', currency: 'CNY', title: 'CPI (YoY)', impact: 'High', link: 'https://www.forexfactory.com/calendar' }
                ];
            }

            filterEvents();
        }

        document.addEventListener('DOMContentLoaded', initCalendarPage);
    })();
</script>
@endpush
@endsection
