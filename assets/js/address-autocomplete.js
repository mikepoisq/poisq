// address-autocomplete.js — Photon автокомплит для поля адреса

(function() {
    const PHOTON_URL = 'https://photon.komoot.io/api/';
    let debounceTimer = null;
    let dropdown = null;
    let currentInput = null;

    function getCountryCode() {
        const hidden = document.getElementById('country');
        if (hidden && hidden.value) return hidden.value;
        const select = document.getElementById('countrySelect');
        if (select && select.value) return select.value;
        if (currentInput && currentInput.dataset.country) return currentInput.dataset.country;
        return null;
    }

    function getCityName() {
        const cityEl = document.getElementById('selectedCityName');
        if (cityEl && cityEl.textContent.trim()) return cityEl.textContent.trim();
        const citySelect = document.getElementById('citySelect') || document.querySelector('select[name="city_id"]');
        if (citySelect) {
            const opt = citySelect.options[citySelect.selectedIndex];
            if (opt && opt.text) return opt.text.trim();
        }
        return null;
    }

    function getLang(countryCode) {
        const map = {
            'fr':'fr','de':'de','es':'es','it':'it','gb':'en','us':'en',
            'ca':'en','au':'en','nl':'nl','be':'fr','ch':'de','at':'de',
            'pt':'pt','gr':'el','pl':'pl','cz':'cs','se':'sv','no':'no',
            'dk':'da','fi':'fi','ie':'en','nz':'en','ae':'ar','il':'he',
            'tr':'tr','th':'th','jp':'ja','kr':'ko','sg':'en','hk':'zh',
            'mx':'es','br':'pt','ar':'es','cl':'es','co':'es','za':'en',
            'ru':'ru','ua':'uk','by':'ru','kz':'ru'
        };
        return map[countryCode] || 'en';
    }

    function formatAddress(feature) {
        const p = feature.properties || {};
        const parts = [];
        if (p.street) parts.push(p.street);
        if (p.housenumber) parts.push(p.housenumber);
        const city = p.city || p.town || p.village || '';
        if (city) parts.push(city);
        if (p.postcode) parts.push(p.postcode);
        return parts.join(', ');
    }

    function createDropdown() {
        const div = document.createElement('div');
        div.id = 'address-dropdown';
        div.style.cssText = `
            position:absolute; z-index:9999;
            background:#fff; border:1px solid #D1D5DB;
            border-radius:8px; box-shadow:0 4px 16px rgba(0,0,0,0.12);
            max-height:260px; overflow-y:auto;
            font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
            font-size:14px; min-width:100%;
        `;
        document.body.appendChild(div);
        return div;
    }

    function positionDropdown(input) {
        const rect = input.getBoundingClientRect();
        dropdown.style.top  = (rect.bottom + window.scrollY + 4) + 'px';
        dropdown.style.left = (rect.left + window.scrollX) + 'px';
        dropdown.style.width = rect.width + 'px';
    }

    function hideDropdown() {
        if (dropdown) dropdown.style.display = 'none';
    }

    function showResults(features, input) {
        if (!dropdown) dropdown = createDropdown();
        dropdown.innerHTML = '';
        if (!features || !features.length) { hideDropdown(); return; }

        features.forEach(feature => {
            const label = formatAddress(feature);
            if (!label) return;
            const div = document.createElement('div');
            div.style.cssText = 'padding:10px 14px; cursor:pointer; border-bottom:1px solid #F3F4F6; color:#1F2937; line-height:1.4;';
            div.textContent = label;
            div.addEventListener('mouseenter', () => div.style.background = '#F0F7FF');
            div.addEventListener('mouseleave', () => div.style.background = '#fff');
            div.addEventListener('mousedown', (e) => {
                e.preventDefault();
                justSelected = true;
                input.value = label;
                hideDropdown();
                input.focus();
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
            dropdown.appendChild(div);
        });

        if (!dropdown.children.length) { hideDropdown(); return; }
        positionDropdown(input);
        dropdown.style.display = 'block';
    }

    async function fetchSuggestions(query, countryCode, cityName) {
        const lang = getLang(countryCode);
        const fullQuery = cityName ? `${query} ${cityName}` : query;
        // Строим URL вручную чтобы layer передавался правильно
        let url = `${PHOTON_URL}?q=${encodeURIComponent(fullQuery)}&limit=6&lang=${lang}&layer=house&layer=street`;

        try {
            const res = await fetch(url);
            const data = await res.json();
            let features = data.features || [];
            // Фильтруем по стране (countrycode приходит в верхнем регистре)
            if (countryCode && features.length) {
                features = features.filter(f => {
                    const cc = (f.properties.countrycode || '').toLowerCase();
                    return cc === countryCode.toLowerCase();
                });
            }
            return features;
        } catch(e) { return []; }
    }

    let justSelected = false;

    function initInput(input) {
        input.setAttribute('autocomplete', 'off');
        input.addEventListener('input', () => {
            if (justSelected) { justSelected = false; return; }
            currentInput = input;
            const val = input.value.trim();
            clearTimeout(debounceTimer);
            if (val.length < 3) { hideDropdown(); return; }
            debounceTimer = setTimeout(async () => {
                const country = getCountryCode();
                const city = getCityName();
                const results = await fetchSuggestions(val, country, city);
                showResults(results, input);
            }, 250);
        });
        input.addEventListener('blur', () => setTimeout(hideDropdown, 150));
        input.addEventListener('focus', () => { currentInput = input; });
    }

    function init() {
        document.querySelectorAll('input[name="address"], input#address').forEach(initInput);
        document.addEventListener('click', (e) => {
            if (dropdown && !dropdown.contains(e.target)) hideDropdown();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
