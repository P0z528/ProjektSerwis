document.addEventListener("DOMContentLoaded", function () {
    const selectTyp = document.getElementById("select-typ");
    const selectModel = document.getElementById("select-model");
    const kontenerCzesci = document.getElementById("kontener-czesci");
    const sumaPln = document.getElementById("suma-pln");
    const btnOdrzuc = document.getElementById("btn-odrzuc");

    // Funkcja zabezpieczająca cudzysłowy w atrybutach HTML value
    function escapeHtml(str) {
        return str.replace(/"/g, '&quot;');
    }

    // --- OBSŁUGA ZAKŁADEK I ZAPAMIĘTYWANIE (LOCAL STORAGE) ---
    const tabButtons = document.querySelectorAll('#v-pills-tab button');

    // 1. Zapisywanie wyboru po kliknięciu
    tabButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            // Zapisujemy ID klikniętej zakładki do pamięci przeglądarki
            localStorage.setItem('recepcjaAktywnaZakladka', this.id);

            // Kolorowanie (żeby ładnie wyglądało)
            tabButtons.forEach(b => {
                b.classList.remove('active', 'bg-purple', 'text-white');
                b.classList.add('text-dark');
            });
            this.classList.add('active', 'bg-purple', 'text-white');
        });
    });

    // 2. Odtwarzanie wyboru po przeładowaniu strony (np. po wywaleniu błędu)
    const zapisanaZakladka = localStorage.getItem('recepcjaAktywnaZakladka');
    if (zapisanaZakladka) {
        const aktywnyPrzycisk = document.getElementById(zapisanaZakladka);
        if (aktywnyPrzycisk) {
            // Skrypt automatycznie "klika" w odpowiednią zakładkę, zanim jeszcze to zauważysz
            aktywnyPrzycisk.click();
        }
    }

    // --- OBSŁUGA ZAKŁADKI OBSŁUGA (Dynamiczne ładowanie modeli i części) ---

    // 1. Wybór typu -> Pobierz modele przez fetch API
    selectTyp.addEventListener("change", function () {
        const typ = this.value;
        if (!typ) {
            selectModel.innerHTML = '<option value="">Najpierw wybierz typ...</option>';
            selectModel.disabled = true;
            return;
        }

        fetch(`/api/modele/${encodeURIComponent(typ)}`)
            .then(res => res.json())
            .then(data => {
                selectModel.innerHTML = '<option value="">Wybierz model...</option>';

                const oldModel = selectModel.getAttribute('data-old');

                data.forEach(model => {
                    const selected = (model === oldModel) ? 'selected' : '';
                    // TUTAJ DODANO escapeHtml(model)
                    selectModel.innerHTML += `<option value="${escapeHtml(model)}" ${selected}>${model}</option>`;
                });
                selectModel.disabled = false;

                if (oldModel) {
                    selectModel.dispatchEvent(new Event('change'));
                    selectModel.removeAttribute('data-old');
                }
            });
    });

    // 2. Wybór modelu -> Pobierz cennik części i usług
    selectModel.addEventListener("change", function () {
        const model = this.value;
        if (!model) {
            kontenerCzesci.innerHTML = '<p class="text-muted small m-0 ps-2">Wybierz model urządzenia...</p>';
            przeliczKoszty();
            return;
        }

        fetch(`/api/czesci/${encodeURIComponent(model)}`)
            .then(res => res.json())
            .then(data => {
                kontenerCzesci.innerHTML = "";
                if(data.length === 0) {
                    kontenerCzesci.innerHTML = '<p class="text-muted small m-0 ps-2">Brak zdefiniowanych części dla tego modelu.</p>';
                    przeliczKoszty();
                    return;
                }

                // Odczytujemy tablicę wcześniej zaznaczonych usług/części
                const oldCzesci = JSON.parse(kontenerCzesci.getAttribute('data-old') || '[]');

                data.forEach((item, index) => {
                    const checked = oldCzesci.includes(item.nazwa_czesci) ? 'checked' : '';
                    kontenerCzesci.innerHTML += `
                        <div class="col-md-6 mb-2">
                            <div class="form-check">
                                <input class="form-check-input czesc-checkbox" type="checkbox" name="czesci[]" value="${item.nazwa_czesci}" data-cena="${item.cena}" id="chk-${index}" ${checked}>
                                <label class="form-check-input-label small" for="chk-${index}">
                                    ${item.nazwa_czesci} (${parseFloat(item.cena).toFixed(2)} PLN)
                                </label>
                            </div>
                        </div>
                    `;
                });

                // Podpięcie nasłuchiwania zmian pod nowo wygenerowane checkboxy
                document.querySelectorAll(".czesc-checkbox").forEach(cb => {
                    cb.addEventListener("change", przeliczKoszty);
                });

                // Przelicz koszty na starcie (wykryje przywrócone checkboxy)
                przeliczKoszty();
            });
    });

    // 3. Przeliczanie kosztów w locie
    function przeliczKoszty() {
        let suma = 0.0;
        document.querySelectorAll(".czesc-checkbox:checked").forEach(cb => {
            suma += parseFloat(cb.getAttribute("data-cena"));
        });
        sumaPln.innerText = `${suma.toFixed(2)} PLN`;
    }

    // 4. Resetowanie kosztów po wyczyszczeniu formularza
    btnOdrzuc.addEventListener("click", function() {
        // 1. Twarde czyszczenie wpisanych wartości
        document.querySelector('input[name="imie"]').value = "";
        document.querySelector('input[name="nazwisko"]').value = "";
        document.querySelector('input[name="telefon"]').value = "";
        document.querySelector('input[name="numer_seryjny"]').value = "";
        const terminEl = document.getElementById("select-termin");
        if (terminEl) terminEl.value = "";
        const kierunkowyEl = document.querySelector('select[name="kierunkowy"]');
        if (kierunkowyEl) kierunkowyEl.value = "+48";
        selectTyp.value = "";

        // 2. Czyszczenie dynamicznych list (modele i części)
        selectModel.innerHTML = '<option value="">Najpierw wybierz typ...</option>';
        selectModel.disabled = true;

        kontenerCzesci.innerHTML = '<p class="text-muted small m-0 ps-2">Wybierz model urządzenia...</p>';
        kontenerCzesci.setAttribute('data-old', '[]');

        // 3. Wyzerowanie podsumowania kwoty
        przeliczKoszty();

        // 4. Posprzątanie czerwonych ramek i błędów walidacji po poprzedniej próbie
        document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        document.querySelectorAll('.text-danger').forEach(el => el.remove());
    });

    // --- ŁADOWANIE WOLNYCH TERMINÓW NAPRAWY ---
    const selectTermin = document.getElementById("select-termin");
    if (selectTermin) {
        const oldTermin = selectTermin.getAttribute('data-old');
        fetch('/api/recepcja/terminy')
            .then(res => res.json())
            .then(data => {
                selectTermin.innerHTML = '<option value="">Wybierz termin...</option>';
                if (data.length === 0) {
                    selectTermin.innerHTML = '<option value="">Brak wolnych terminów</option>';
                    return;
                }
                data.forEach(t => {
                    const selected = (t.data === oldTermin) ? 'selected' : '';
                    selectTermin.innerHTML += `<option value="${t.data}" ${selected}>${t.etykieta}</option>`;
                });
            })
            .catch(() => {
                selectTermin.innerHTML = '<option value="">Błąd ładowania terminów</option>';
            });
    }

    // --- INICJALIZACJA PO ODŚWIEŻENIU STRONY ---
    // Jeśli po powrocie z walidacji typ jest już wybrany, sztucznie wywołujemy zdarzenie zmiany, aby załadować resztę struktur
    if (selectTyp.value) {
        selectTyp.dispatchEvent(new Event('change'));
    }

    // 5. Obsługa pola "Inny" w katalogu urządzeń
    const selectKatalogTyp = document.getElementById("select-katalog-typ");
    const manualTypFrame = document.getElementById("manual-typ-frame");

    if(selectKatalogTyp) {
        selectKatalogTyp.addEventListener("change", function() {
            if(this.value === "Inny") {
                manualTypFrame.classList.remove("d-none");
            } else {
                manualTypFrame.classList.add("d-none");
            }
        });
    }
    // --- OBSŁUGA ZAKŁADKI KATALOG (Dodawanie Części) ---
    const selectKatalogTypCzesc = document.getElementById("select-katalog-typ-czesc");
    const selectKatalogModelCzesc = document.getElementById("select-katalog-model-czesc");

    if (selectKatalogTypCzesc && selectKatalogModelCzesc) {
        selectKatalogTypCzesc.addEventListener("change", function () {
            const typ = this.value;
            if (!typ) {
                selectKatalogModelCzesc.innerHTML = '<option value="">Najpierw wybierz typ...</option>';
                selectKatalogModelCzesc.disabled = true;
                return;
            }

            fetch(`/api/modele/${encodeURIComponent(typ)}`)
                .then(res => res.json())
                .then(data => {
                    selectKatalogModelCzesc.innerHTML = '<option value="">Wybierz model...</option>';

                    const oldModel = selectKatalogModelCzesc.getAttribute('data-old');

                    data.forEach(model => {
                        const selected = (model === oldModel) ? 'selected' : '';
                        // TUTAJ TEŻ DODANO escapeHtml(model)
                        selectKatalogModelCzesc.innerHTML += `<option value="${escapeHtml(model)}" ${selected}>${model}</option>`;
                    });

                    selectKatalogModelCzesc.disabled = false;
                    selectKatalogModelCzesc.removeAttribute('data-old');
                });
        });

        // Jeśli wejdziesz na stronę, a typ był już wybrany (bo np. wyświetlił się alert o nadpisaniu)
        if (selectKatalogTypCzesc.value) {
            selectKatalogTypCzesc.dispatchEvent(new Event('change'));
        }
    }

    // --- SPRAWDZANIE STATUSU ZLECENIA ---
    const btnCheckStatus = document.getElementById('btn-check-status');
    const inputZlecenie = document.getElementById('status-zlecenie');
    const inputTelefon = document.getElementById('status-telefon');
    const statusResult = document.getElementById('status-result');

    if (btnCheckStatus) {
        btnCheckStatus.addEventListener('click', function() {
            const nrZlecenia = inputZlecenie.value.trim();
            const telefon = inputTelefon.value.trim();

            if (!nrZlecenia || !telefon) {
                statusResult.classList.remove('d-none');
                statusResult.innerHTML = '<div class="text-danger small fw-bold">✕ Podaj numer zlecenia i telefon.</div>';
                return;
            }

            statusResult.classList.remove('d-none');
            statusResult.innerHTML = '<div class="text-center text-muted"><div class="spinner-border spinner-border-sm"></div> Szukam...</div>';

            const url = `/api/recepcja/status-naprawy?zlecenie=${encodeURIComponent(nrZlecenia)}&telefon=${encodeURIComponent(telefon)}`;

            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const s = data.data[0];
                        let html = `
                            <div class="fw-bold mb-2 text-success">✓ Znaleziono zlecenie #${s.id_zlecenia}</div>
                            <div class="border-top border-light pt-2 mt-2">
                                <div class="fw-bold text-dark">${s.model} <span class="small text-muted">(SN: ${s.numer_seryjny})</span></div>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1">${s.status}</span>
                                    <span class="fw-bold small">${s.koszt} PLN</span>
                                </div>
                            </div>
                        `;
                        statusResult.innerHTML = html;
                    } else {
                        statusResult.innerHTML = `<div class="text-danger small fw-bold">✕ ${data.message}</div>`;
                    }
                })
                .catch(err => {
                    statusResult.innerHTML = '<div class="text-danger small">Wystąpił błąd łączenia z serwerem.</div>';
                });
        });
    }

    // --- KOMPRESJA WIELU ZDJĘĆ W TLE ---
    const inputZdjecia = document.querySelector('input[type="file"][name="zdjecia[]"]');

    function kompresujPlik(file) {
        return new Promise((resolve) => {
            // Pliki <= 2MB lub nieobrazkowe puszczamy bez zmian
            if (!file.type.startsWith('image/') || file.size <= 2 * 1024 * 1024) {
                resolve(file);
                return;
            }

            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = function(e) {
                const img = new Image();
                img.src = e.target.result;
                img.onload = function() {
                    const canvas = document.createElement('canvas');
                    const MAX_WIDTH = 1200;
                    const MAX_HEIGHT = 1200;
                    let width = img.width;
                    let height = img.height;

                    if (width > height) {
                        if (width > MAX_WIDTH) { height *= MAX_WIDTH / width; width = MAX_WIDTH; }
                    } else {
                        if (height > MAX_HEIGHT) { width *= MAX_HEIGHT / height; height = MAX_HEIGHT; }
                    }

                    canvas.width = width;
                    canvas.height = height;
                    canvas.getContext('2d').drawImage(img, 0, 0, width, height);

                    canvas.toBlob(function(blob) {
                        resolve(new File([blob], file.name.replace(/\.[^/.]+$/, "") + ".jpg", {
                            type: 'image/jpeg',
                            lastModified: Date.now()
                        }));
                    }, 'image/jpeg', 0.8);
                };
                img.onerror = () => resolve(file);
            };
            reader.onerror = () => resolve(file);
        });
    }

    if (inputZdjecia) {
        inputZdjecia.addEventListener('change', async function(event) {
            const files = Array.from(event.target.files);
            if (files.length === 0) return;

            // Jeśli żaden plik nie wymaga kompresji, nic nie robimy
            if (!files.some(f => f.size > 2 * 1024 * 1024)) return;

            const skompresowane = await Promise.all(files.map(kompresujPlik));

            const dataTransfer = new DataTransfer();
            skompresowane.forEach(f => dataTransfer.items.add(f));
            inputZdjecia.files = dataTransfer.files;
        });
    }
});
