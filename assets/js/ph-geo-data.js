/**
 * Philippine Geographic Data & Standard Form Presets
 * Provides comprehensive provinces, cities/municipalities, and standardized civil/sacramental presets.
 */

(function (root, factory) {
    if (typeof define === 'function' && define.amd) {
        define([], factory);
    } else if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.PHGeo = factory();
    }
}(typeof self !== 'undefined' ? self : this, function () {

    // All 82 Philippine Provinces + Metro Manila (NCR)
    const PROVINCES = [
        "Metro Manila (NCR)", "Rizal", "Cavite", "Laguna", "Batangas", "Bulacan", "Pampanga",
        "Abra", "Agusan del Norte", "Agusan del Sur", "Aklan", "Albay", "Antique", "Apayao",
        "Aurora", "Basilan", "Bataan", "Batanes", "Benguet", "Biliran", "Bohol", "Bukidnon",
        "Cagayan", "Camarines Norte", "Camarines Sur", "Camiguin", "Capiz", "Catanduanes",
        "Cebu", "Cotabato", "Davao de Oro", "Davao del Norte", "Davao del Sur", "Davao Occidental",
        "Davao Oriental", "Dinagat Islands", "Eastern Samar", "Guimaras", "Ifugao", "Ilocos Norte",
        "Ilocos Sur", "Iloilo", "Isabela", "Kalinga", "La Union", "Lanao del Norte", "Lanao del Sur",
        "Leyte", "Maguindanao del Norte", "Maguindanao del Sur", "Marinduque", "Masbate",
        "Misamis Occidental", "Misamis Oriental", "Mountain Province", "Negros Occidental",
        "Negros Oriental", "Northern Samar", "Nueva Ecija", "Nueva Vizcaya", "Occidental Mindoro",
        "Oriental Mindoro", "Palawan", "Pangasinan", "Quezon", "Quirino", "Romblon",
        "Samar (Western Samar)", "Sarangani", "Siquijor", "Sorsogon", "South Cotabato",
        "Southern Leyte", "Sultan Kudarat", "Sulu", "Surigao del Norte", "Surigao del Sur",
        "Tarlac", "Tawi-Tawi", "Zambales", "Zamboanga del Norte", "Zamboanga del Sur", "Zamboanga Sibugay"
    ];

    // Prominent and common Philippine Cities/Municipalities grouped by Province
    const CITIES_BY_PROVINCE = {
        "Rizal": [
            "Angono", "Antipolo City", "Baras", "Binangonan", "Cainta", "Cardona", "Jalajala",
            "Morong", "Pililla", "Rodriguez (Montalban)", "San Mateo", "Tanay", "Taytay", "Teresa"
        ],
        "Metro Manila (NCR)": [
            "Manila", "Quezon City", "Caloocan", "Las Piñas", "Makati", "Malabon", "Mandaluyong",
            "Marikina", "Muntinlupa", "Navotas", "Parañaque", "Pasay", "Pasig", "Pateros",
            "San Juan", "Taguig", "Valenzuela"
        ],
        "Laguna": [
            "Alaminos", "Bay", "Biñan City", "Cabuyao City", "Calamba City", "Calauan", "Cavinti",
            "Famy", "Kalayaan", "Liliw", "Los Baños", "Luisiana", "Lumban", "Mabitac", "Magdalena",
            "Majayjay", "Nagcarlan", "Paete", "Pagsanjan", "Pakil", "Pangil", "Pila", "Rizal",
            "San Pablo City", "San Pedro City", "Santa Cruz", "Santa Maria", "Santa Rosa City",
            "Siniloan", "Victoria"
        ],
        "Cavite": [
            "Alfonso", "Amadeo", "Bacoor City", "Carmona City", "Cavite City", "Dasmariñas City",
            "General Emilio Aguinaldo", "General Mariano Alvarez", "General Trias City", "Imus City",
            "Indang", "Kawit", "Magallanes", "Maragondon", "Mendez", "Naic", "Noveleta", "Rosario",
            "Silang", "Tagaytay City", "Tanza", "Ternate", "Trece Martires City"
        ],
        "Batangas": [
            "Agoncillo", "Alitagtag", "Balayan", "Balete", "Batangas City", "Bauan", "Calaca City",
            "Calatagan", "Cuenca", "Ibaan", "Laurel", "Lemery", "Lian", "Lipa City", "Lobo",
            "Mabini", "Malvar", "Mataasnakahoy", "Nasugbu", "Padre Garcia", "Rosario", "San Jose",
            "San Juan", "San Luis", "San Nicolas", "San Pascual", "Santa Teresita", "Santo Tomas City",
            "Taal", "Talisay", "Tanauan City", "Taysan", "Tingloy", "Tuy"
        ],
        "Bulacan": [
            "Angat", "Balagtas", "Baliwag City", "Bocaue", "Bulakan", "Bustos", "Calumpit",
            "Doña Remedios Trinidad", "Guiguinto", "Hagonoy", "Malolos City", "Marilao",
            "Meycauayan City", "Norzagaray", "Obando", "Pandi", "Paombong", "Plaridel",
            "Pulilan", "San Ildefonso", "San Jose del Monte City", "San Miguel", "San Rafael", "Santa Maria"
        ],
        "Pampanga": [
            "Angeles City", "Apalit", "Arayat", "Bacolor", "Candaba", "Floridablanca", "Guagua",
            "Lubao", "Mabalacat City", "Macabebe", "Magalang", "Masantol", "Mexico", "Minalin",
            "Porac", "San Fernando City", "San Luis", "San Simon", "Santa Ana", "Santa Rita", "Santo Tomas", "Sasmuan"
        ],
        "Quezon": [
            "Lucena City", "Tayabas City", "Candelaria", "Sariaya", "Tiaong", "Dolores", "Lucban",
            "Pagbilao", "Atimonan", "Gumaca", "Lopez", "Calauag", "Infanta", "Real", "General Nakar"
        ],
        "Cebu": [
            "Cebu City", "Mandaue City", "Lapu-Lapu City", "Talisay City", "Toledo City", "Danao City",
            "Carcar City", "Naga City", "Bogo City", "Consolacion", "Liloan", "Minglanilla", "Cordova"
        ],
        "Iloilo": [
            "Iloilo City", "Passi City", "Oton", "Pavia", "Santa Barbara", "Leganes", "Zarraga",
            "Dumangas", "Pototan", "Barotac Nuevo", "Miagao", "Guimbal", "Tigbauan"
        ],
        "Pangasinan": [
            "Dagupan City", "San Carlos City", "Urdaneta City", "Alaminos City", "Lingayen",
            "Binmaley", "Calasiao", "Mangaldan", "Malasiqui", "Bayambang", "Rosales", "Tayug"
        ],
        "Benguet": [
            "Baguio City", "La Trinidad", "Itogon", "Tuba", "Sablan", "Tublay", "Buguias", "Mankayan"
        ],
        "Davao del Sur": [
            "Davao City", "Digos City", "Bansalan", "Hagonoy", "Magsaysay", "Matanao", "Padada", "Santa Cruz"
        ]
    };

    // Flattened Popular Cities/Municipalities for quick autocomplete
    const ALL_POPULAR_CITIES = [
        "Angono, Rizal", "Antipolo City, Rizal", "Binangonan, Rizal", "Cainta, Rizal", "Taytay, Rizal",
        "San Mateo, Rizal", "Rodriguez, Rizal", "Morong, Rizal", "Tanay, Rizal", "Cardona, Rizal",
        "Baras, Rizal", "Pililla, Rizal", "Teresa, Rizal", "Jalajala, Rizal",
        "Manila", "Quezon City", "Caloocan", "Pasig", "Makati", "Taguig", "Mandaluyong", "Marikina",
        "Parañaque", "Pasay", "Las Piñas", "Muntinlupa", "Valenzuela", "Malabon", "Navotas", "San Juan",
        "Cebu City", "Davao City", "Baguio City", "Iloilo City", "Bacolod City", "Cagayan de Oro City",
        "Zamboanga City", "Angeles City", "San Fernando, Pampanga", "Olongapo City", "Batangas City",
        "Lipa City", "Calamba City, Laguna", "Santa Rosa City, Laguna", "Biñan City, Laguna", "San Pedro City, Laguna",
        "Bacoor City, Cavite", "Imus City, Cavite", "Dasmariñas City, Cavite", "General Trias, Cavite", "Tagaytay City",
        "San Jose del Monte City, Bulacan", "Malolos City, Bulacan", "Meycauayan City, Bulacan",
        "Lucena City, Quezon", "Dagupan City, Pangasinan", "Naga City, Camarines Sur", "Legazpi City, Albay",
        "Tacloban City, Leyte", "General Santos City", "Butuan City", "Puerto Princesa City, Palawan"
    ];

    // Standard Civil and Church Presets
    const PRESETS = {
        citizenships: [
            "Filipino", "American", "Canadian", "Australian", "British", "Chinese", "Japanese",
            "Korean", "Spanish", "German", "Italian", "Singaporean", "Dual Citizen (Filipino-Foreign)"
        ],
        religions: [
            "Philippine Independent Church (IFI)", "Roman Catholic", "Christian / Evangelical",
            "Baptist", "Methodist", "Iglesia ni Cristo", "Seventh-day Adventist", "United Church of Christ in the Philippines (UCCP)",
            "Anglican / Episcopal", "Pentecostal", "Islam", "None / Agnostic"
        ],
        relationships: [
            "Father", "Mother", "Surviving Parent", "Legal Guardian", "Person Having Legal Charge", "Relative"
        ],
        dissolutions: [
            "Death of Spouse", "Court Decree of Annulment", "Court Decree of Nullity", "Foreign Divorce Decree", "Legal Separation"
        ],
        civilStatuses: [
            "Single", "Widowed", "Divorced", "Annulled"
        ],
        officerTitles: [
            "Parish Priest / Rector", "Diocesan Bishop", "Priest", "Bishop", "Deacon",
            "Municipal Mayor", "City Mayor", "Presiding Judge", "Consul General"
        ],
        degreesOfRelationship: [
            "None", "First Degree", "Second Degree", "Third Degree", "Fourth Degree"
        ]
    };

    /**
     * Injects HTML datalist elements into the DOM if they don't already exist.
     */
    function injectDatalists() {
        const lists = [
            { id: 'dl-ph-provinces', items: PROVINCES },
            { id: 'dl-ph-all-cities', items: ALL_POPULAR_CITIES },
            { id: 'dl-citizenships', items: PRESETS.citizenships },
            { id: 'dl-religions', items: PRESETS.religions },
            { id: 'dl-relationships', items: PRESETS.relationships },
            { id: 'dl-dissolutions', items: PRESETS.dissolutions },
            { id: 'dl-officer-titles', items: PRESETS.officerTitles },
            { id: 'dl-degrees-rel', items: PRESETS.degreesOfRelationship }
        ];

        lists.forEach(({ id, items }) => {
            if (!document.getElementById(id)) {
                const dl = document.createElement('datalist');
                dl.id = id;
                let html = '';
                items.forEach(item => {
                    html += `<option value="${escapeHtml(item)}">`;
                });
                dl.innerHTML = html;
                document.body.appendChild(dl);
            }
        });

        // Dynamic city datalist linked to current selected province
        if (!document.getElementById('dl-ph-municipalities')) {
            const dl = document.createElement('datalist');
            dl.id = 'dl-ph-municipalities';
            document.body.appendChild(dl);
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    /**
     * Updates the municipality datalist based on the selected province.
     */
    function updateMunicipalityDatalist(provinceName, customDatalistId) {
        const dl = document.getElementById(customDatalistId || 'dl-ph-municipalities');
        if (!dl) return;

        const cleanProv = (provinceName || '').trim();
        // Try exact match or match ignoring case
        let cities = CITIES_BY_PROVINCE[cleanProv];
        if (!cities) {
            const matchKey = Object.keys(CITIES_BY_PROVINCE).find(k => k.toLowerCase() === cleanProv.toLowerCase());
            if (matchKey) cities = CITIES_BY_PROVINCE[matchKey];
        }

        if (cities && cities.length > 0) {
            let html = '';
            cities.forEach(c => {
                html += `<option value="${escapeHtml(c)}">`;
            });
            dl.innerHTML = html;
        } else {
            // Fallback: Populate with popular cities if province has no specific list
            let html = '';
            ALL_POPULAR_CITIES.forEach(c => {
                html += `<option value="${escapeHtml(c)}">`;
            });
            dl.innerHTML = html;
        }
    }

    /**
     * Wires up cascading behavior between a Province input and a Municipality/City input.
     */
    function linkProvinceAndCity(provinceInput, cityInput, datalistId = 'dl-ph-municipalities') {
        if (!provinceInput || !cityInput) return;

        provinceInput.setAttribute('list', 'dl-ph-provinces');
        provinceInput.setAttribute('autocomplete', 'off');
        cityInput.setAttribute('list', datalistId);
        cityInput.setAttribute('autocomplete', 'off');

        function onProvinceChange() {
            updateMunicipalityDatalist(provinceInput.value, datalistId);
        }

        provinceInput.addEventListener('input', onProvinceChange);
        provinceInput.addEventListener('change', onProvinceChange);

        // Initial populate based on current province value
        if (provinceInput.value) {
            updateMunicipalityDatalist(provinceInput.value, datalistId);
        } else {
            updateMunicipalityDatalist('Rizal', datalistId); // Default to Rizal
        }
    }

    /**
     * Automatically scans DOM inputs and binds appropriate datalists.
     */
    function autoBindStandardFields(root) {
        const context = root || document;

        // 1. Province & Municipality pairs
        const provInputs = context.querySelectorAll('input[name="province"], input#in-province, input[data-geo="province"]');
        const cityInputs = context.querySelectorAll('input[name="municipality"], input[name="city"], input#in-municipality, input#in-city, input[data-geo="city"]');

        if (provInputs.length > 0 && cityInputs.length > 0) {
            linkProvinceAndCity(provInputs[0], cityInputs[0], 'dl-ph-municipalities');
        } else {
            provInputs.forEach(p => p.setAttribute('list', 'dl-ph-provinces'));
            cityInputs.forEach(c => c.setAttribute('list', 'dl-ph-all-cities'));
        }

        // 2. Place of Birth / Dissolved place / General location inputs
        context.querySelectorAll('input[name*="pob"], input[id*="pob"], input[name*="birth_place"], input[id*="birth_place"], input[name*="dissolved_place"]').forEach(el => {
            if (!el.hasAttribute('list')) el.setAttribute('list', 'dl-ph-all-cities');
            el.setAttribute('autocomplete', 'off');
        });

        // 3. Citizenship inputs
        context.querySelectorAll('input[name*="citizenship"], input[id*="citizenship"]').forEach(el => {
            if (!el.hasAttribute('list')) el.setAttribute('list', 'dl-citizenships');
            el.setAttribute('autocomplete', 'off');
        });

        // 4. Religion inputs
        context.querySelectorAll('input[name*="religion"], input[id*="religion"]').forEach(el => {
            if (!el.hasAttribute('list')) el.setAttribute('list', 'dl-religions');
            el.setAttribute('autocomplete', 'off');
        });

        // 5. Consent / Advice Relationship inputs
        context.querySelectorAll('input[name*="consent_rel"], input[id*="consent-rel"], input[name*="degree_relationship"], input[id*="degree-rel"]').forEach(el => {
            if (el.name.includes('degree')) {
                if (!el.hasAttribute('list')) el.setAttribute('list', 'dl-degrees-rel');
            } else {
                if (!el.hasAttribute('list')) el.setAttribute('list', 'dl-relationships');
            }
            el.setAttribute('autocomplete', 'off');
        });

        // 6. Dissolved How inputs
        context.querySelectorAll('input[name*="dissolved_how"], input[id*="diss-how"]').forEach(el => {
            if (!el.hasAttribute('list')) el.setAttribute('list', 'dl-dissolutions');
            el.setAttribute('autocomplete', 'off');
        });

        // 7. Officer Title inputs
        context.querySelectorAll('input[name*="officer_title"], input[id*="officer-title"], input[name*="priest_title"]').forEach(el => {
            if (!el.hasAttribute('list')) el.setAttribute('list', 'dl-officer-titles');
            el.setAttribute('autocomplete', 'off');
        });
    }

    // Auto-initialize when DOM is ready
    function init() {
        injectDatalists();
        autoBindStandardFields(document);

        // Dynamic observer for modals and drawers
        if (window.MutationObserver) {
            const observer = new MutationObserver(mutations => {
                for (let mutation of mutations) {
                    if (mutation.addedNodes.length) {
                        mutation.addedNodes.forEach(node => {
                            if (node.nodeType === 1) {
                                autoBindStandardFields(node);
                            }
                        });
                    }
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return {
        PROVINCES,
        CITIES_BY_PROVINCE,
        ALL_POPULAR_CITIES,
        PRESETS,
        injectDatalists,
        updateMunicipalityDatalist,
        linkProvinceAndCity,
        autoBindStandardFields
    };
}));
