(() => {
    const fiche = document.getElementById('fiche');
    if (!fiche) return;
    const { id, csrf, next, api, apiNom, apiPosition } = fiche.dataset;
    const form = document.getElementById('adresse-form');
    const message = document.getElementById('message');
    const btnOk = document.getElementById('btn-ok');

    const show = (text, kind = 'ok') => {
        message.className = `flash ${kind}`;
        message.textContent = text;
    };

    const post = (url, body) => fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrf, 'Content-Type': 'application/json', Accept: 'application/json' },
        body: body ? JSON.stringify(body) : undefined,
    }).then(async (res) => ({ res, data: await res.json().catch(() => ({})) }));

    const formData = () => Object.fromEntries(new FormData(form).entries());

    // Un champ modifie invalide l'etat "adresse correcte" tant que l'adresse n'est pas enregistree.
    let dirty = false;
    form.addEventListener('input', () => {
        dirty = true;
        btnOk.dataset.ok = '0';
        btnOk.textContent = 'Adresse correcte';
    });

    const enregistrer = async () => {
        const { res, data } = await post(api, formData());
        if (res.status === 422) {
            const violations = (data.violations || []).map((v) => v.title).join(' ');
            show(violations || 'Adresse invalide.', 'warn');
            return false;
        }
        if (!res.ok) { show('Enregistrement impossible.', 'warn'); return false; }
        dirty = false;
        const avert = (data.avertissements || []).join(' ');
        show(avert ? `Adresse enregistrée. ${avert}` : 'Adresse enregistrée.', avert ? 'warn' : 'ok');
        return true;
    };

    // --- nom de l'etablissement ---
    const nomForm = document.getElementById('nom-form');
    const btnNomOk = document.getElementById('btn-nom-ok');
    let nomDirty = false;
    nomForm.addEventListener('input', () => {
        nomDirty = true;
        btnNomOk.dataset.ok = '0';
        btnNomOk.textContent = 'Valider le nom';
    });

    const enregistrerNom = async () => {
        const { res, data } = await post(apiNom, Object.fromEntries(new FormData(nomForm).entries()));
        if (res.status === 422) {
            show((data.violations || []).map((v) => v.title).join(' ') || 'Nom invalide.', 'warn');
            return false;
        }
        if (!res.ok) { show('Enregistrement du nom impossible.', 'warn'); return false; }
        nomDirty = false;
        show('Nom enregistré.');
        return true;
    };

    document.getElementById('btn-nom-enregistrer').addEventListener('click', enregistrerNom);

    btnNomOk.addEventListener('click', async () => {
        if (nomDirty && !(await enregistrerNom())) return;
        const valeur = btnNomOk.dataset.ok !== '1';
        const { res } = await post(`/api/marche/${id}/nom-ok?valeur=${valeur ? 1 : 0}`);
        if (!res.ok) return show('Action impossible.', 'warn');
        btnNomOk.dataset.ok = valeur ? '1' : '0';
        btnNomOk.textContent = valeur ? 'Nom validé ✓' : 'Valider le nom';
    });

    // --- adresse ---
    document.getElementById('btn-enregistrer').addEventListener('click', enregistrer);

    btnOk.addEventListener('click', async () => {
        if (dirty && !(await enregistrer())) return;
        const valeur = btnOk.dataset.ok !== '1';
        const { res } = await post(`/api/marche/${id}/adresse-ok?valeur=${valeur ? 1 : 0}`);
        if (!res.ok) return show('Action impossible.', 'warn');
        btnOk.dataset.ok = valeur ? '1' : '0';
        btnOk.textContent = valeur ? 'Adresse validée ✓' : 'Adresse correcte';
    });

    document.getElementById('btn-traite').addEventListener('click', async () => {
        if (nomDirty && !(await enregistrerNom())) return;
        if (dirty && !(await enregistrer())) return;
        const { res, data } = await post(`/api/marche/${id}/traite`);
        if (!res.ok) return show(data.message || 'Action impossible.', 'warn');
        location.href = next;
    });

    // --- recherches externes (nouvel onglet), a partir des valeurs actuellement saisies ---
    const requete = () => {
        const v = (name, f) => (f.elements[name].value || '').trim();
        return [
            v('denomination', nomForm),
            v('numVoie', form), v('typeVoie', form), v('nomVoie', form), v('codePostal', form), v('commune', form),
        ].filter(Boolean).join(' ');
    };
    const ouvrir = (base) => window.open(`${base}${encodeURIComponent(requete())}`, '_blank', 'noopener');
    document.getElementById('btn-google').addEventListener('click', () => ouvrir('https://www.google.com/search?q='));
    document.getElementById('btn-maps').addEventListener('click', () => ouvrir('https://www.google.com/maps?q='));

    // --- position ---
    const positionForm = document.getElementById('position-form');
    const nombre = (valeur) => {
        const texte = String(valeur ?? '').trim().replace(',', '.');
        return texte === '' || Number.isNaN(Number(texte)) ? null : Number(texte);
    };

    document.getElementById('btn-coller-position').addEventListener('click', async () => {
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

    document.getElementById('btn-position').addEventListener('click', async () => {
        const { res, data } = await post(apiPosition, {
            latitude: nombre(positionForm.elements.latitude.value),
            longitude: nombre(positionForm.elements.longitude.value),
        });
        if (res.status === 422) {
            return show((data.violations || []).map((v) => v.title).join(' ') || 'Position invalide.', 'warn');
        }
        if (!res.ok) return show('Enregistrement de la position impossible.', 'warn');
        show('Position enregistrée.');
    });
})();
