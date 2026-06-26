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

    // NORMALIZACJA NUMERU KIERUNKOWEGO
    function normalizujKierunkowy(wartosc) {
        let k = (wartosc || '').replace(/\s/g, '').trim();
        if (k === '') return '+48';
        if (/^[0-9]+$/.test(k)) return '+' + k;
        if (!k.startsWith('+')) k = '+' + k.replace(/^\+*/, '');
        return k;
    }

    const inputKierunkowy = document.getElementById('input-kierunkowy');
    const inputTelefon = document.querySelector('input[name="telefon"]');
    const formZlecenie = document.getElementById('form-zlecenie');

    // Walidacja numeru kierunkowego
    if (inputKierunkowy) {
        // Jeśli pole przy ładowaniu nie ma plusa, od razu go wstawiamy
        if (!inputKierunkowy.value.startsWith('+')) {
            inputKierunkowy.value = '+' + inputKierunkowy.value.replace(/[^0-9]/g, '');
        }

        inputKierunkowy.addEventListener('input', function() {
            // 1. Zostawiamy w wartości tylko i wyłącznie cyfry
            let sameCyfry = this.value.replace(/[^0-9]/g, '');

            // 2. Sklejamy od nowa: niezdejmowalny '+' i ucięte cyfry (max 3, bo z plusem daje to 4 znaki)
            this.value = '+' + sameCyfry.substring(0, 3);
        });

        // Gdy użytkownik opuści pole
        inputKierunkowy.addEventListener('blur', function () {
            // Jeśli użytkownik usunął cyfry i zostawił samego plusa, wracamy do +48
            if (this.value === '+') {
                this.value = '+48';
            } else {
                this.value = normalizujKierunkowy(this.value);
            }
        });
    }

    // Walidacja numeru telefonu (bez zmian - wycina litery)
    if (inputTelefon) {
        inputTelefon.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    }

    if (formZlecenie) {
        formZlecenie.addEventListener('submit', function () {
            if (inputKierunkowy) {
                inputKierunkowy.value = normalizujKierunkowy(inputKierunkowy.value);
            }
        });
    }

    // OBSŁUGA ZAKŁADEK I ZAPAMIĘTYWANIE (LOCAL STORAGE)
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

    // OBSŁUGA ZAKŁADKI OBSŁUGA (Dynamiczne ładowanie modeli i części)

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
            kontenerCzesci.innerHTML = '<div class="col-12"><p class="text-muted small m-0 ps-2">Wybierz model urządzenia...</p></div>';
            przeliczKoszty();
            return;
        }

        fetch(`/api/czesci/${encodeURIComponent(model)}`)
            .then(res => res.json())
            .then(data => {
                kontenerCzesci.innerHTML = "";
                if(data.length === 0) {
                    kontenerCzesci.innerHTML = '<div class="col-12"><p class="text-muted small m-0 ps-2">Brak zdefiniowanych części dla tego modelu.</p></div>';
                    przeliczKoszty();
                    return;
                }

                // Odczytujemy tablicę wcześniej zaznaczonych usług/części
                const oldCzesci = JSON.parse(kontenerCzesci.getAttribute('data-old') || '[]');

                // Filtrowanie zwróconych danych
                const czesci = data.filter(item => item.typ === 'Część');
                const uslugi = data.filter(item => item.typ === 'Usługa');

                let html = '';
                let index = 0; // globalny licznik dla unikalnych atrybutów "id" i "for"

                // Funkcja pomocnicza generująca HTML dla checkboxów
                const dodajCheckboxy = (lista) => {
                    lista.forEach(item => {
                        const checked = oldCzesci.includes(item.nazwa_czesci) ? 'checked' : '';
                        html += `
                            <div class="col-md-6 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input czesc-checkbox" type="checkbox" name="czesci[]" value="${escapeHtml(item.nazwa_czesci)}" data-cena="${item.cena}" id="chk-poz-${index}" ${checked}>
                                    <label class="form-check-input-label small" for="chk-poz-${index}">
                                        ${escapeHtml(item.nazwa_czesci)} (${parseFloat(item.cena).toFixed(2)} PLN)
                                    </label>
                                </div>
                            </div>
                        `;
                        index++;
                    });
                };

                // Dodawanie bloku "Części sprzętowe"
                if (czesci.length > 0) {
                    html += '<div class="col-12"><h6 class="fw-bold small text-muted mb-2 mt-1 border-bottom pb-1" style="color: #6b7280 !important;">Części sprzętowe</h6></div>';
                    dodajCheckboxy(czesci);
                }

                // Dodawanie bloku "Usługi serwisowe"
                if (uslugi.length > 0) {
                    // Dodatkowy margines górny, jeśli wcześniej wyświetliliśmy części
                    const mt = czesci.length > 0 ? 'mt-3' : 'mt-1';
                    html += `<div class="col-12"><h6 class="fw-bold small text-muted mb-2 ${mt} border-bottom pb-1" style="color: #6b7280 !important;">Usługi serwisowe</h6></div>`;
                    dodajCheckboxy(uslugi);
                }

                kontenerCzesci.innerHTML = html;

                // Podpięcie nasłuchiwania zmian pod nowo wygenerowane checkboxy
                document.querySelectorAll(".czesc-checkbox").forEach(cb => {
                    cb.addEventListener("change", przeliczKoszty);
                });

                // Przelicz koszty na starcie (wykryje przywrócone checkboxy po błędnej walidacji)
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
        if (typeof dataTransferZdjecia !== 'undefined') {
            dataTransferZdjecia = new DataTransfer();
            const inputZdjeciaUkryty = document.getElementById('input-zdjecia-ukryty');
            const zdjeciaLista = document.getElementById('zdjecia-lista');
            const btnDodaj = document.getElementById('btn-dodaj-zdjecie');

            if (inputZdjeciaUkryty) inputZdjeciaUkryty.files = dataTransferZdjecia.files;
            if (zdjeciaLista) zdjeciaLista.innerHTML = '';
            if (btnDodaj) btnDodaj.querySelector('span').innerText = "+ Dodaj zdjęcie";
        }

        document.querySelector('input[name="imie"]').value = "";
        document.querySelector('input[name="nazwisko"]').value = "";
        document.querySelector('input[name="telefon"]').value = "";
        document.querySelector('input[name="numer_seryjny"]').value = "";
        const terminEl = document.getElementById("select-termin");
        if (terminEl) terminEl.value = "";
        const kierunkowyEl = document.querySelector('input[name="kierunkowy"]');
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

    // ŁADOWANIE WOLNYCH TERMINÓW NAPRAWY
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

    // INICJALIZACJA PO ODŚWIEŻENIU STRONY
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
    // OBSŁUGA ZAKŁADKI KATALOG (Dodawanie Części)
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

    // SPRAWDZANIE STATUSU ZLECENIA
    const btnCheckStatus = document.getElementById('btn-check-status');
    const inputZlecenie = document.getElementById('status-zlecenie');
    const inputNumerSeryjny = document.getElementById('status-numer-seryjny');
    const statusResult = document.getElementById('status-result');

    if (btnCheckStatus) {
        btnCheckStatus.addEventListener('click', function() {
            const nrZlecenia = inputZlecenie.value.trim();
            const numerSeryjny = inputNumerSeryjny.value.trim();

            if (!nrZlecenia || !numerSeryjny) {
                statusResult.classList.remove('d-none');
                statusResult.innerHTML = '<div class="text-danger small fw-bold">Podaj numer zlecenia oraz numer seryjny.</div>';
                return;
            }

            statusResult.classList.remove('d-none');
            statusResult.innerHTML = '<div class="text-center text-muted"><div class="spinner-border spinner-border-sm"></div> Szukam...</div>';

            const url = `/api/recepcja/status-naprawy?zlecenie=${encodeURIComponent(nrZlecenia)}&numer_seryjny=${encodeURIComponent(numerSeryjny)}`;

            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const s = data.data[0];
                        const wlasciciel = (s.imie && s.nazwisko) ? `${s.imie} ${s.nazwisko}` : '—';
                        let html = `
                            <div class="fw-bold mb-2 text-success">Znaleziono zlecenie #${s.id_zlecenia}</div>
                            <div class="border-top border-light pt-2 mt-2">
                                <div class="small text-muted mb-1">Właściciel: <span class="fw-bold text-dark">${wlasciciel}</span></div>
                                <div class="fw-bold text-dark">${s.model} <span class="small text-muted">(SN: ${s.numer_seryjny})</span></div>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1">${s.status}</span>
                                    <span class="fw-bold small">${s.koszt} PLN</span>
                                </div>
                            </div>
                        `;
                        statusResult.innerHTML = html;
                    } else {
                        statusResult.innerHTML = `<div class="text-danger small fw-bold"> ${data.message}</div>`;
                    }
                })
                .catch(err => {
                    statusResult.innerHTML = '<div class="text-danger small">Wystąpił błąd łączenia z serwerem.</div>';
                });
        });
    }

   // ZARZĄDZANIE ZDJĘCIAMI I KOMPRESJA (Nowy mechanizm)
    const inputZdjeciaUkryty = document.getElementById('input-zdjecia-ukryty');
    const btnDodajZdjecie = document.getElementById('btn-dodaj-zdjecie');
    const zdjeciaLista = document.getElementById('zdjecia-lista');

    // Globalny obiekt przetrzymujący dodane zdjęcia
    let dataTransferZdjecia = new DataTransfer();

    function kompresujPlik(file) {
        return new Promise((resolve) => {
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

    if (btnDodajZdjecie && inputZdjeciaUkryty) {
        btnDodajZdjecie.addEventListener('click', () => {
            // Tworzymy tymczasowy input, aby nie nadpisywać starych wyborów
            const tempInput = document.createElement('input');
            tempInput.type = 'file';
            tempInput.multiple = true;
            tempInput.accept = 'image/png, image/jpeg, image/jpg, image/webp';

            tempInput.addEventListener('change', async function(e) {
                const files = Array.from(e.target.files);
                if (files.length === 0) return;

                // Animacja ładowania
                const spanTekst = btnDodajZdjecie.querySelector('span');
                const originalText = spanTekst.innerText;
                spanTekst.innerHTML = '<div class="spinner-border spinner-border-sm"></div>';
                btnDodajZdjecie.disabled = true;

                // Kompresujemy i wrzucamy do głównego worka
                const skompresowane = await Promise.all(files.map(kompresujPlik));
                skompresowane.forEach(file => {
                    dataTransferZdjecia.items.add(file);
                });

                // Podpinamy work do ukrytego formularza wysyłki
                inputZdjeciaUkryty.files = dataTransferZdjecia.files;
                renderujPodgladZdjec();

                btnDodajZdjecie.disabled = false;
            });

            tempInput.click();
        });
    }

    function renderujPodgladZdjec() {
        if (!zdjeciaLista) return;
        zdjeciaLista.innerHTML = '';

        Array.from(dataTransferZdjecia.files).forEach((file, index) => {
            const reader = new FileReader();

            const element = document.createElement('div');
            element.className = 'position-relative border rounded overflow-hidden bg-light shadow-sm';
            element.style.width = '70px';
            element.style.height = '70px';

            const img = document.createElement('img');
            img.style.objectFit = 'cover';
            img.style.width = '100%';
            img.style.height = '100%';

            const btnUsun = document.createElement('button');
            btnUsun.type = 'button';
            btnUsun.className = 'btn btn-danger btn-sm position-absolute top-0 end-0 p-0 d-flex align-items-center justify-content-center rounded-circle m-1';
            btnUsun.style.width = '20px';
            btnUsun.style.height = '20px';
            btnUsun.style.fontSize = '12px';
            btnUsun.innerHTML = '&times;'; // Znak 'X'

            // Logika usuwania pojedynczego zdjęcia
            btnUsun.onclick = function() {
                const nowePliki = new DataTransfer();
                Array.from(dataTransferZdjecia.files).forEach((f, i) => {
                    if (i !== index) nowePliki.items.add(f);
                });
                dataTransferZdjecia = nowePliki;
                inputZdjeciaUkryty.files = dataTransferZdjecia.files;
                renderujPodgladZdjec();
            };

            reader.onload = function(e) {
                img.src = e.target.result;
            }
            reader.readAsDataURL(file);

            element.appendChild(img);
            element.appendChild(btnUsun);
            zdjeciaLista.appendChild(element);
        });

        // Zmiana tekstu przycisku w zależności od liczby zdjęć
        if (btnDodajZdjecie) {
            btnDodajZdjecie.querySelector('span').innerText = dataTransferZdjecia.files.length > 0 ? "+ Kolejne zdjęcie" : "+ Dodaj zdjęcie";
        }
    }

    // MODAL POTWIERDZENIA ZLECENIA (po dodaniu nowego zlecenia)
    const zlecenieModalEl = document.getElementById('zlecenieModal');
    if (zlecenieModalEl) {
        let wydrukKlikniety = false;
        let zezwolNaZamkniecie = false;

        const zlecenieModal = new bootstrap.Modal(zlecenieModalEl);
        zlecenieModal.show();

        const btnWydruk = document.getElementById('btn-wydruk-zlecenie');
        if (btnWydruk) {
            btnWydruk.addEventListener('click', function () {
                wydrukKlikniety = true;
            });
        }

        zlecenieModalEl.addEventListener('hide.bs.modal', function (event) {
            if (wydrukKlikniety || zezwolNaZamkniecie) {
                return;
            }
            event.preventDefault();
            if (confirm('Czy na pewno chcesz zamknąć bez drukowania potwierdzenia?')) {
                zezwolNaZamkniecie = true;
                zlecenieModal.hide();
            }
        });
    }

    // EDYCJA / USUWANIE POZYCJI CENNIKA
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const editTyp = document.getElementById('edit-katalog-typ');
    const editModel = document.getElementById('edit-katalog-model');
    const listaPozycji = document.getElementById('lista-pozycji-cennika');

    function escapeAttr(str) {
        return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function renderPusto(text) {
        listaPozycji.innerHTML = `<tr><td colspan="3" class="text-center text-muted small py-3">${text}</td></tr>`;
    }

    if (editTyp && editModel && listaPozycji) {
        // Typ -> modele
        editTyp.addEventListener('change', function () {
            const typ = this.value;
            renderPusto('Wybierz model, aby wyświetlić pozycje cennika.');
            if (!typ) {
                editModel.innerHTML = '<option value="">Najpierw wybierz typ...</option>';
                editModel.disabled = true;
                return;
            }
            fetch(`/api/modele/${encodeURIComponent(typ)}`)
                .then(res => res.json())
                .then(data => {
                    editModel.innerHTML = '<option value="">Wybierz model...</option>';
                    data.forEach(model => {
                        editModel.innerHTML += `<option value="${escapeAttr(model)}">${model}</option>`;
                    });
                    editModel.disabled = false;
                });
        });

        // Model -> pozycje cennika
        editModel.addEventListener('change', function () {
            const model = this.value;
            if (!model) {
                renderPusto('Wybierz model, aby wyświetlić pozycje cennika.');
                return;
            }
            listaPozycji.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm"></div> Ładowanie...</td></tr>';

            fetch(`/api/katalog/${encodeURIComponent(model)}`)
                .then(res => res.json())
                .then(data => {
                    if (!data.length) {
                        renderPusto('Brak pozycji cennika dla tego modelu.');
                        return;
                    }
                    listaPozycji.innerHTML = '';
                    data.forEach(poz => {
                        const cena = parseFloat(poz.cena).toFixed(2);
                        const czescSel = poz.typ === 'Część' ? 'selected' : '';
                        const uslugaSel = poz.typ === 'Usługa' ? 'selected' : '';
                        listaPozycji.innerHTML += `
                            <tr>
                                <td class="small">${poz.nazwa_czesci}</td>
                                <td>
                                    <form action="/recepcja/czesc/${poz.id}" method="POST" class="d-flex gap-1 m-0 align-items-center">
                                        <input type="hidden" name="_token" value="${csrfToken}">
                                        <input type="number" step="0.01" min="0" name="cena" value="${cena}" class="form-control form-control-sm bg-light border" style="max-width: 110px;" required>
                                        <select name="typ_pozycji" class="form-select form-select-sm bg-light border" style="max-width: 110px;">
                                            <option value="Część" ${czescSel}>Część</option>
                                            <option value="Usługa" ${uslugaSel}>Usługa</option>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-success py-1 px-2">Zapisz</button>
                                    </form>
                                </td>
                                <td class="text-end">
                                    <form action="/recepcja/czesc/${poz.id}/usun" method="POST" class="m-0" onsubmit="return confirm('Na pewno usunąć tę pozycję z cennika?');">
                                        <input type="hidden" name="_token" value="${csrfToken}">
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2">Usuń</button>
                                    </form>
                                </td>
                            </tr>
                        `;
                    });
                })
                .catch(() => renderPusto('Błąd ładowania pozycji cennika.'));
        });
    }
});
