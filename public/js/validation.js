(() => {
    const page = document.getElementById('fiche');
    if (!page) return;
    const { csrf, id: ficheId, next, apiTraite, apiLier, apiCandidats } = page.dataset;
    const pageMessage = document.getElementById('message-page');

    const post = (url, body) => fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrf, 'Content-Type': 'application/json', Accept: 'application/json' },
        body: body ? JSON.stringify(body) : undefined,
    }).then(async (res) => ({ res, data: await res.json().catch(() => ({})) }));

    const violations = (data, defaut) => (data.violations || []).map((v) => v.title).join(' ') || data.message || defaut;
    const nombre = (valeur) => {
        const texte = String(valeur ?? '').trim().replace(',', '.');
        return texte === '' || Number.isNaN(Number(texte)) ? null : Number(texte);
    };
    // reste sur la fiche courante (groupe compris) apres une liaison / dissociation
    const recharger = () => { location.href = `${next}?fiche=${ficheId}`; };

    // ---------------------------------------------------------------- une fiche du groupe
    const initCard = (card) => {
        const { id, apiAdresse, apiNom, apiPosition, apiDelier } = card.dataset;
        const $ = (sel) => card.querySelector(sel);
        const nomForm = $('.nom-form');
        const adresseForm = $('.adresse-form');
        const positionForm = $('.position-form');
        const btnNomOk = $('.btn-nom-ok');
        const btnAdresseOk = $('.btn-adresse-ok');
        const message = $('.message');
        let nomDirty = false;
        let adresseDirty = false;

        const show = (text, kind = 'ok') => { message.className = `message flash ${kind}`; message.textContent = text; };
        const setOk = (btn, ok, on, off) => { btn.dataset.ok = ok ? '1' : '0'; btn.textContent = ok ? on : off; };

        // un champ modifie invalide l'etat "valide" tant qu'il n'est pas enregistre
        nomForm.addEventListener('input', () => { nomDirty = true; setOk(btnNomOk, false, '', 'Valider le nom'); });
        adresseForm.addEventListener('input', () => { adresseDirty = true; setOk(btnAdresseOk, false, '', 'Adresse correcte'); });

        const enregistrerNom = async () => {
            const { res, data } = await post(apiNom, Object.fromEntries(new FormData(nomForm).entries()));
            if (!res.ok) { show(res.status === 422 ? violations(data, 'Nom invalide.') : 'Enregistrement du nom impossible.', 'warn'); return false; }
            nomDirty = false;
            show('Nom enregistré.');
            return true;
        };
        const enregistrerAdresse = async () => {
            const { res, data } = await post(apiAdresse, Object.fromEntries(new FormData(adresseForm).entries()));
            if (!res.ok) { show(res.status === 422 ? violations(data, 'Adresse invalide.') : 'Enregistrement impossible.', 'warn'); return false; }
            adresseDirty = false;
            const avert = (data.avertissements || []).join(' ');
            show(avert ? `Adresse enregistrée. ${avert}` : 'Adresse enregistrée.', avert ? 'warn' : 'ok');
            return true;
        };

        $('.btn-nom-enregistrer').addEventListener('click', enregistrerNom);
        $('.btn-adresse-enregistrer').addEventListener('click', enregistrerAdresse);

        btnNomOk.addEventListener('click', async () => {
            if (nomDirty && !(await enregistrerNom())) return;
            const valeur = btnNomOk.dataset.ok !== '1';
            const { res } = await post(`/api/marche/${id}/nom-ok?valeur=${valeur ? 1 : 0}`);
            if (!res.ok) return show('Action impossible.', 'warn');
            setOk(btnNomOk, valeur, 'Nom validé ✓', 'Valider le nom');
        });
        btnAdresseOk.addEventListener('click', async () => {
            if (adresseDirty && !(await enregistrerAdresse())) return;
            const valeur = btnAdresseOk.dataset.ok !== '1';
            const { res } = await post(`/api/marche/${id}/adresse-ok?valeur=${valeur ? 1 : 0}`);
            if (!res.ok) return show('Action impossible.', 'warn');
            setOk(btnAdresseOk, valeur, 'Adresse validée ✓', 'Adresse correcte');
        });

        // position
        $('.btn-coller-position').addEventListener('click', async () => {
            if (!navigator.clipboard || !navigator.clipboard.readText) {
                return show("La lecture du presse-papier n'est pas disponible sur ce navigateur.", 'warn');
            }
            try {
                const parties = (await navigator.clipboard.readText()).split(/[,;]\s*(?=-?\d)|\s+/).filter(Boolean);
                const [lat, lng] = parties.map(nombre);
                if (parties.length !== 2 || lat === null || lng === null) {
                    return show('Le presse-papier ne contient pas de coordonnées valides (format attendu : latitude, longitude).', 'warn');
                }
                positionForm.elements.latitude.value = lat;
                positionForm.elements.longitude.value = lng;
            } catch {
                show('Impossible de lire le presse-papier, vérifiez les autorisations du navigateur.', 'warn');
            }
        });
        $('.btn-position').addEventListener('click', async () => {
            const { res, data } = await post(apiPosition, {
                latitude: nombre(positionForm.elements.latitude.value),
                longitude: nombre(positionForm.elements.longitude.value),
            });
            if (!res.ok) return show(res.status === 422 ? violations(data, 'Position invalide.') : 'Enregistrement de la position impossible.', 'warn');
            show('Position enregistrée.');
        });

        // recherches externes (nouvel onglet), a partir des valeurs actuellement saisies
        const requete = () => {
            const v = (name, f) => (f.elements[name].value || '').trim();
            return [
                v('denomination', nomForm),
                v('numVoie', adresseForm), v('typeVoie', adresseForm), v('nomVoie', adresseForm),
                v('codePostal', adresseForm), v('commune', adresseForm),
            ].filter(Boolean).join(' ');
        };
        const ouvrir = (base) => window.open(`${base}${encodeURIComponent(requete())}`, '_blank', 'noopener');
        $('.btn-google').addEventListener('click', () => ouvrir('https://www.google.com/search?q='));
        $('.btn-maps').addEventListener('click', () => ouvrir('https://www.google.com/maps?q='));

        // dissocier ce doublon
        const btnDelier = $('.btn-delier');
        if (btnDelier) {
            btnDelier.addEventListener('click', async () => {
                if (!window.confirm('Retirer cette fiche du groupe de doublons ?')) return;
                const { res } = await post(apiDelier);
                if (!res.ok) return show('Action impossible.', 'warn');
                recharger();
            });
        }

        // enregistre ce qui est en attente ; false si une sauvegarde a echoue
        return { flush: async () => (!nomDirty || await enregistrerNom()) && (!adresseDirty || await enregistrerAdresse()) };
    };

    const cards = [...document.querySelectorAll('.fiche-card')].map(initCard);

    // ---------------------------------------------------------------- traitement du groupe
    document.getElementById('btn-traite').addEventListener('click', async () => {
        for (const card of cards) {
            if (!(await card.flush())) return;
        }
        const { res, data } = await post(apiTraite);
        if (!res.ok) {
            pageMessage.className = 'flash warn';
            pageMessage.textContent = data.message || 'Action impossible.';
            return;
        }
        location.href = next;
    });

    // ---------------------------------------------------------------- doublons
    const resultats = document.getElementById('doublons-resultats');
    const tableEl = document.getElementById('doublons-table');
    const btnCp = document.getElementById('btn-search-cp');
    const btnCommune = document.getElementById('btn-search-commune');
    const btnDenomination = document.getElementById('btn-search-denomination');
    let dataTable = null;

    const francais = {
        emptyTable: 'Aucune fiche trouvée.', zeroRecords: 'Aucune fiche ne correspond.',
        info: '_START_ à _END_ sur _TOTAL_ fiches', infoEmpty: '0 fiche', infoFiltered: '(filtré sur _MAX_)',
        lengthMenu: 'Afficher _MENU_ fiches', search: 'Filtrer :', loadingRecords: 'Chargement…',
        paginate: { first: '«', previous: '‹', next: '›', last: '»' },
    };
    const texte = (v) => (v === null || v === undefined ? '' : String(v));
    const echapper = (v) => texte(v).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    const vider = () => {
        if (dataTable) { dataTable.destroy(); dataTable = null; }
        $(tableEl).find('tbody').remove();
        resultats.hidden = true;
    };

    const rechercher = async (champ) => {
        pageMessage.className = '';
        pageMessage.textContent = '';
        const res = await fetch(`${apiCandidats}?champ=${champ}`, { headers: { Accept: 'application/json' } });
        if (!res.ok) {
            pageMessage.className = 'flash warn';
            pageMessage.textContent = 'Recherche impossible.';
            return;
        }
        const lignes = await res.json();
        vider();
        resultats.hidden = false;
        dataTable = $(tableEl).DataTable({
            data: lignes,
            columns: [
                { data: 'id' },
                { data: 'denominationNormalise', render: echapper },
                { data: 'numVoie', render: echapper },
                { data: 'typeVoie', render: echapper },
                { data: 'nomVoie', render: echapper },
                { data: 'codePostal', render: echapper },
                { data: 'commune', render: echapper },
                { data: 'codens', render: echapper },
                { data: 'id', orderable: false, searchable: false,
                  render: (id) => `<button type="button" class="btn-lier" data-id="${Number(id)}">Lier</button>` },
            ],
            pageLength: 10,
            order: [[0, 'asc']],
            language: francais,
        });
    };

    btnCp.addEventListener('click', () => rechercher('cp'));
    btnCommune.addEventListener('click', () => rechercher('commune'));
    btnDenomination.addEventListener('click', () => rechercher('denomination'));
    document.getElementById('btn-search-reset').addEventListener('click', vider);

    // bouton "Lier" de chaque ligne : lie cette fiche au groupe de la fiche courante
    $(tableEl).on('click', '.btn-lier', async (event) => {
        const { res, data } = await post(apiLier, { ids: [Number(event.currentTarget.dataset.id)] });
        if (!res.ok) {
            pageMessage.className = 'flash warn';
            pageMessage.textContent = violations(data, 'Liaison impossible.');
            return;
        }
        recharger();
    });
})();
