// ===== CONFIG =====
const CONFIG = {
    MIN_SEARCH_LENGTH: 2,
    HISTORY_LIMIT: 10,
    DEBOUNCE_DELAY: 150,
    SCROLL_OFFSET: 20,
    // 🔧 РАЗНЫЕ ТАЙМИНГИ ДЛЯ iOS И ANDROID
    SCROLL_DELAY_1: /Android/i.test(navigator.userAgent) ? 600 : 400,
    SCROLL_DELAY_2: /Android/i.test(navigator.userAgent) ? 1200 : 800
};

// ===== DATA =====
const searchSuggestions = [
    'Александр Петров', 'Мария Иванова', 'Дмитрий Сидоров',
    'Елена Козлова', 'Андрей Новиков', 'Ольга Соколова',
    'Русский педиатр', 'Русский юрист', 'Русская няня'
];

// ===== STATE =====
let searchHistory = JSON.parse(localStorage.getItem('poisq_history') || '[]');

// ===== DOM CACHE =====
let searchInput, searchClear, searchResults, recentSearchesList, suggestionsList, clearHistory;

// ===== INIT =====
function initSearch() {
    searchInput = document.getElementById('searchInput');
    searchClear = document.getElementById('searchClear');
    searchResults = document.getElementById('searchResults');
    recentSearchesList = document.getElementById('recentSearchesList');
    suggestionsList = document.getElementById('suggestionsList');
    clearHistory = document.getElementById('clearHistory');
    
    if (!searchInput) return;
    
    // 🔧 ОСНОВНЫЕ ОБРАБОТЧИКИ
    searchInput.addEventListener('focus', handleSearchFocus);
    searchInput.addEventListener('input', handleSearchInput);
    searchInput.addEventListener('keydown', handleSearchKeydown);
    searchInput.addEventListener('blur', handleSearchBlur);
    
    // 🔧 КРЕСТИК ОЧИСТКИ — с проверкой существования
    if (searchClear) {
        searchClear.addEventListener('click', handleClearSearch);
        // 🔧 iOS Safari: дополнительная обработка touch
        searchClear.addEventListener('touchstart', (e) => {
            e.preventDefault();
            handleClearSearch(e);
        });
    }
    
    if (clearHistory) {
        clearHistory.addEventListener('click', clearSearchHistory);
    }
    
    document.addEventListener('click', (e) => {
        if (searchResults && !searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            hideResults();
        }
    });
    
    // 🔧 ПРОВЕРЯЕМ КРЕСТИК ПРИ ЗАГРУЗКЕ (для results.php с заполненным полем)
    setTimeout(() => {
        updateClearButton();
    }, 100);
}

// ===== SEARCH HANDLERS =====
function handleSearchFocus() {
    searchInput.placeholder = '';
    
    // 🔧 ПОДСКРОЛЛ: ТОЛЬКО ДЛЯ index.php
    const isIndexPage = window.location.pathname === '/index.php' || 
                        window.location.pathname === '/' || 
                        window.location.pathname.endsWith('index.php');
    
    if (isIndexPage) {
        setTimeout(() => {
            scrollSearchToTop();
        }, CONFIG.SCROLL_DELAY_1);
        
        setTimeout(() => {
            scrollSearchToTop();
        }, CONFIG.SCROLL_DELAY_2);
        
        if (/Android/i.test(navigator.userAgent)) {
            let resizeTimeout;
            const handleResize = () => {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(() => {
                    scrollSearchToTop();
                }, 200);
            };
            window.addEventListener('resize', handleResize, { once: true });
        }
    }
    
    const query = searchInput.value.trim();
    
    if (query.length >= CONFIG.MIN_SEARCH_LENGTH) {
        const hasSuggestions = renderSuggestions(query);
        showResults(hasSuggestions);
    } else {
        hideResults();
    }
}

function handleSearchBlur() {
    if (searchInput.value.trim() === '') {
        searchInput.placeholder = 'Заграница нам поможет!';
    }
}

function handleSearchInput(e) {
    const query = e.target.value.trim();
    
    // 🔧 КРЕСТИК — ОБНОВЛЯЕМ ПРИ КАЖДОМ ВВОДЕ
    updateClearButton();
    
    if (query.length >= CONFIG.MIN_SEARCH_LENGTH) {
        const hasSuggestions = renderSuggestions(query);
        showResults(hasSuggestions);
    } else {
        hideResults();
    }
}

function handleSearchKeydown(e) {
    if (e.key === 'Enter') {
        const query = searchInput.value.trim();
        if (query) {
            addToHistory(query);
            hideResults();
        }
    } else if (e.key === 'Escape') {
        hideResults();
        searchInput.blur();
    }
}

// 🔧 ФУНКЦИЯ ОЧИСТКИ ПОЛЯ
function handleClearSearch(e) {
    e.stopPropagation();
    e.preventDefault();
    searchInput.value = '';
    updateClearButton();
    hideResults();
    searchInput.focus();
}

// ===== UI FUNCTIONS =====
function showResults(hasContent) {
    if (searchResults) {
        hasContent ? searchResults.classList.add('visible') : searchResults.classList.remove('visible');
    }
}

function hideResults() {
    if (searchResults) {
        searchResults.classList.remove('visible');
    }
}

// 🔧 ФУНКЦИЯ ПОКАЗА/СКРЫТИЯ КРЕСТИКА — УНИВЕРСАЛЬНАЯ
function updateClearButton() {
    if (!searchClear || !searchInput) return;
    
    const hasValue = searchInput.value.trim().length > 0;
    
    // 🔧 iOS Safari: используем оба метода для надёжности
    if (hasValue) {
        searchClear.classList.add('visible');
        searchClear.style.display = 'flex';
    } else {
        searchClear.classList.remove('visible');
        searchClear.style.display = 'none';
    }
}

// 🔧 СКРОЛЛ ДЛЯ iOS (ТОЛЬКО index.php)
function scrollSearchToTop() {
    const searchBox = document.querySelector('.search-container');
    if (!searchBox) return;
    
    const boxRect = searchBox.getBoundingClientRect();
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;
    const targetScroll = scrollTop + boxRect.top - CONFIG.SCROLL_OFFSET;
    
    window.scrollTo(0, targetScroll);
    if (document.body) {
        document.body.scrollTop = targetScroll;
        document.body.scrollTo(0, targetScroll);
    }
    if (document.documentElement) {
        document.documentElement.scrollTop = targetScroll;
        document.documentElement.scrollTo(0, targetScroll);
    }
    
    if (/Android/i.test(navigator.userAgent)) {
        searchBox.scrollIntoView({ behavior: 'auto', block: 'start' });
        setTimeout(() => {
            window.scrollTo(0, targetScroll);
            if (document.body) document.body.scrollTop = targetScroll;
            if (document.documentElement) document.documentElement.scrollTop = targetScroll;
        }, 100);
    }
}

// ===== RENDER FUNCTIONS =====
function renderRecentSearches() {
    if (!recentSearchesList) return false;
    if (searchHistory.length === 0) { recentSearchesList.innerHTML = ''; return false; }
    
    recentSearchesList.innerHTML = '<div style="font-size:12px;font-weight:600;color:var(--text-light);padding:12px 16px;text-transform:uppercase;">Недавние</div>' +
        searchHistory.map(item => '<div class="search-result-item" data-query="' + item + '"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg><span>' + item + '</span></div>').join('');
    
    recentSearchesList.querySelectorAll('.search-result-item').forEach(item => {
        item.addEventListener('click', () => {
            searchInput.value = item.dataset.query;
            addToHistory(item.dataset.query);
            performSearch(item.dataset.query);
        });
    });
    return true;
}

function renderSuggestions(query) {
    if (!suggestionsList) return false;
    
    const filtered = searchSuggestions.filter(s => s.toLowerCase().includes(query.toLowerCase()));
    
    if (filtered.length === 0) {
        suggestionsList.innerHTML = '';
        if (recentSearchesList) recentSearchesList.innerHTML = '';
        return false;
    }
    
    const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    suggestionsList.innerHTML = '<div style="font-size:12px;font-weight:600;color:var(--text-light);padding:12px 16px;text-transform:uppercase;">Подсказки</div>' +
        filtered.map(item => {
            const hl = item.replace(new RegExp('(' + escaped + ')', 'gi'), '<span class="highlight">$1</span>');
            return '<div class="search-result-item" data-query="' + item + '"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg><span>' + hl + '</span></div>';
        }).join('');
    
    suggestionsList.querySelectorAll('.search-result-item').forEach(item => {
        item.addEventListener('click', () => {
            searchInput.value = item.dataset.query;
            addToHistory(item.dataset.query);
            performSearch(item.dataset.query);
        });
    });
    return true;
}

// ===== HISTORY & SEARCH =====
function addToHistory(query) {
    if (!query || !query.trim()) return;
    searchHistory = [query, ...searchHistory.filter(i => i !== query)].slice(0, CONFIG.HISTORY_LIMIT);
    localStorage.setItem('poisq_history', JSON.stringify(searchHistory));
}

function clearSearchHistory() {
    searchHistory = [];
    localStorage.removeItem('poisq_history');
    if (recentSearchesList) recentSearchesList.innerHTML = '';
    if (suggestionsList) suggestionsList.innerHTML = '';
    hideResults();
}

function performSearch(query) {
    const countryField = document.querySelector('input[name="country"]');
    const countryCode = countryField?.value || localStorage.getItem('poisq_country') || 'fr';
    window.location.href = 'results.php?q=' + encodeURIComponent(query) + '&country=' + countryCode;
}

// ===== RUN ON LOAD =====
document.addEventListener('DOMContentLoaded', initSearch);