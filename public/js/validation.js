(() => {
    const page = document.getElementById('fiche');
    if (!page) return;
    const { csrf, id: ficheId, next, apiTraite, apiGarde, apiLier, apiCandidats } = page.dataset;
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
    // mentions de jour / horaire / periode a retirer des produits (meme liste que App\Service\MentionTemporelle)
    const MOTIFS_TEMPS = [
        '(?:lundi|mardi|mercredi|jeudi|vendredi|samedi|dimanche)s?',
        '\\d{1,2}\\s?(?:h|heures?)(?:\\s?\\d{2})?', '\\d{1,2}:\\d{2}',
        'matin(?:s|ée|ées)?', 'apr[eè]s[- ]midi', 'soir(?:s|ée|ées)?', 'midi', 'nocturnes?',
        'janvier', 'f[ée]vrier', 'mars', 'avril', 'mai', 'juin', 'juillet', 'ao[uû]t', 'septembre', 'octobre', 'novembre', 'd[ée]cembre',
        'hebdomadaires?', 'mensuel(?:le)?s?', 'quotidien(?:ne)?s?', 'du mois', 'chaque mois', 'semaines?', "toute l'ann[ée]e",
        '\\d{1,2}/\\d{1,2}',
    ];
    const regexTemps = new RegExp(`(?<![\\p{L}\\d])(?:${MOTIFS_TEMPS.join('|')})(?![\\p{L}\\d])`, 'giu');
    const mentionsTemps = (texte) => {
        const vues = new Map();
        for (const [m] of String(texte ?? '').matchAll(regexTemps)) {
            if (!vues.has(m.toLowerCase())) vues.set(m.toLowerCase(), m);
        }
        return [...vues.values()];
    };
    // reste sur la fiche courante (groupe compris) apres une liaison / dissociation
    const recharger = () => { location.href = `${next}?fiche=${ficheId}`; };

    // ---------------------------------------------------------------- une fiche du groupe
    const initCard = (card) => {
        const { id, apiAdresse, apiNom, apiPosition, apiHoraire, apiHoraireOk, apiProduits, apiProduitsOk, apiSpecificite, apiSpecificiteOk, apiDelier } = card.dataset;
        const $ = (sel) => card.querySelector(sel);
        const nomForm = $('.nom-form');
        const adresseForm = $('.adresse-form');
        const produitsForm = $('.produits-form');
        const positionForm = $('.position-form');
        const btnNomOk = $('.btn-nom-ok');
        const btnAdresseOk = $('.btn-adresse-ok');
        const btnProduitsOk = $('.btn-produits-ok');
        const alerteTemps = $('.mentions-temps');
        const message = $('.message');
        let nomDirty = false;
        let adresseDirty = false;
        let produitsDirty = false;
        const horaireForm = $('.horaire-form');
        const specificiteForm = $('.specificite-form');
        const specificiteVide = $('.specificite-vide');
        const btnSpecificiteOk = $('.btn-specificite-ok');
        let specificiteDirty = false;
        const btnHoraireOk = $('.btn-horaire-ok');
        let horaireDirty = false;

        const show = (text, kind = 'ok') => { message.className = `message flash ${kind}`; message.textContent = text; };
        const setOk = (btn, ok, on, off) => { btn.dataset.ok = ok ? '1' : '0'; btn.textContent = ok ? on : off; };

        // un champ modifie invalide l'etat "valide" tant qu'il n'est pas enregistre
        nomForm.addEventListener('input', () => { nomDirty = true; setOk(btnNomOk, false, '', 'Valider le nom'); });
        adresseForm.addEventListener('input', () => { adresseDirty = true; setOk(btnAdresseOk, false, '', 'Adresse correcte'); });

        // signale en direct les mentions de temps qui restent dans les produits
        const verifierTemps = () => {
            const mentions = mentionsTemps(produitsForm.elements.produits.value);
            alerteTemps.hidden = mentions.length === 0;
            alerteTemps.textContent = mentions.length ? `Mentions de temps à retirer : ${mentions.join(', ')}` : '';
        };
        // produits vides : rien a valider, tant que l'agent n'en ajoute pas
        const produitsVides = $('.produits-vides');
        const basculerVides = () => {
            const vides = produitsForm.elements.produits.value.trim() === '';
            produitsVides.hidden = !vides;
            btnProduitsOk.hidden = vides;
        };
        produitsForm.addEventListener('input', () => {
            produitsDirty = true;
            setOk(btnProduitsOk, false, '', 'Produits corrects');
            verifierTemps();
            basculerVides();
        });
        verifierTemps();

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

        const enregistrerProduits = async () => {
            const { res, data } = await post(apiProduits, Object.fromEntries(new FormData(produitsForm).entries()));
            if (!res.ok) { show(res.status === 422 ? violations(data, 'Produits invalides.') : 'Enregistrement impossible.', 'warn'); return false; }
            produitsDirty = false;
            const avert = (data.avertissements || []).join(' ');
            show(avert ? `Produits enregistrés. ${avert}` : 'Produits enregistrés.', avert ? 'warn' : 'ok');
            return true;
        };

        // horaires : un tableau lundi -> dimanche, un ou plusieurs creneaux par jour ouvert
        const modeleCreneau = $('.modele-creneau');
        const lignesJours = [...horaireForm.querySelectorAll('.jour-ligne')];
        const creneauxDe = (ligne) => [...ligne.querySelectorAll('.creneau')];
        const ajouterCreneau = (ligne, valeurs = {}) => {
            const c = modeleCreneau.content.firstElementChild.cloneNode(true);
            c.querySelector('[name="moment"]').value = valeurs.moment ?? '';
            c.querySelector('[name="heureDebut"]').value = valeurs.heureDebut ?? '';
            c.querySelector('[name="heureFin"]').value = valeurs.heureFin ?? '';
            ligne.querySelector('.creneaux').append(c);
        };
        const lireCreneau = (c) => ({
            moment: c.querySelector('[name="moment"]').value || null,
            heureDebut: c.querySelector('[name="heureDebut"]').value || null,
            heureFin: c.querySelector('[name="heureFin"]').value || null,
        });
        // met en evidence : jour ouvert, jour ouvert sans heure ni moment
        const rafraichirJour = (ligne) => {
            const ouvert = ligne.querySelector('[name="ouvert"]').checked;
            const incomplet = ouvert && creneauxDe(ligne).some((c) => { const v = lireCreneau(c); return !v.moment && !v.heureDebut; });
            ligne.classList.toggle('ouvert', ouvert);
            ligne.classList.toggle('incomplet', incomplet);
            ligne.querySelector('.badge-incomplet').hidden = !incomplet;
            ligne.querySelector('.btn-creneau-ajout').hidden = !ouvert;
            ligne.querySelector('.btn-creneau-copie').hidden = !ouvert;
        };
        const basculerMois = () => {
            const touteAnnee = horaireForm.elements.touteAnnee.checked;
            horaireForm.elements.moisDebut.disabled = touteAnnee;
            horaireForm.elements.moisFin.disabled = touteAnnee;
        };
        const modifie = () => {
            horaireDirty = true;
            setOk(btnHoraireOk, false, '', 'Horaires corrects');
        };
        horaireForm.addEventListener('change', (e) => {
            modifie();
            const ligne = e.target.closest('.jour-ligne');
            if (ligne) {
                // un jour qu'on ouvre a toujours un creneau a remplir ; un jour qu'on ferme n'en garde aucun
                if (e.target.name === 'ouvert') {
                    if (e.target.checked && creneauxDe(ligne).length === 0) ajouterCreneau(ligne);
                    if (!e.target.checked) ligne.querySelector('.creneaux').replaceChildren();
                }
                rafraichirJour(ligne);
            }
            basculerMois();
        });
        horaireForm.addEventListener('click', (e) => {
            const ligne = e.target.closest('.jour-ligne');
            if (!ligne) return;
            if (e.target.closest('.btn-creneau-ajout')) {
                ajouterCreneau(ligne);
            } else if (e.target.closest('.btn-creneau-retrait')) {
                e.target.closest('.creneau').remove();
                // dernier creneau retire : le jour est ferme
                if (creneauxDe(ligne).length === 0) ligne.querySelector('[name="ouvert"]').checked = false;
            } else if (e.target.closest('.btn-creneau-copie')) {
                const source = creneauxDe(ligne).map(lireCreneau);
                for (const autre of lignesJours) {
                    if (autre === ligne || !autre.querySelector('[name="ouvert"]').checked) continue;
                    autre.querySelector('.creneaux').replaceChildren();
                    source.forEach((v) => ajouterCreneau(autre, v));
                    rafraichirJour(autre);
                }
            } else return;
            modifie();
            rafraichirJour(ligne);
        });
        const afficherResultatHoraire = (data) => {
            horaireForm.querySelector('.horaire-resume strong').textContent = data.resume || 'aucun jour';
            const alerte = horaireForm.querySelector('.horaire-avertissements');
            alerte.textContent = (data.avertissements || []).join(' ');
            alerte.hidden = !(data.avertissements || []).length;
        };
        const enregistrerHoraire = async () => {
            const f = horaireForm.elements;
            const creneaux = [];
            for (const ligne of lignesJours) {
                if (!ligne.querySelector('[name="ouvert"]').checked) continue;
                const lus = creneauxDe(ligne).map(lireCreneau);
                // jour ouvert sans creneau saisi : jour connu, horaire inconnu
                (lus.length ? lus : [{ moment: null, heureDebut: null, heureFin: null }]).forEach((v) => creneaux.push({ jour: ligne.dataset.jour, ...v }));
            }
            const { res, data } = await post(apiHoraire, {
                creneaux,
                touteAnnee: f.touteAnnee.checked,
                moisDebut: f.touteAnnee.checked ? null : f.moisDebut.value || null,
                moisFin: f.touteAnnee.checked ? null : f.moisFin.value || null,
            });
            if (!res.ok) { show(res.status === 422 ? violations(data, 'Horaires invalides.') : 'Enregistrement impossible.', 'warn'); return false; }
            horaireDirty = false;
            afficherResultatHoraire(data);
            show((data.avertissements || []).length ? 'Horaires enregistrés, à vérifier : voir l\'alerte.' : 'Horaires enregistrés.', (data.avertissements || []).length ? 'warn' : 'ok');
            return true;
        };
        // specificite : texte libre court ; vide, rien a valider tant que l'agent n'en ajoute pas
        specificiteForm.addEventListener('input', () => {
            specificiteDirty = true;
            setOk(btnSpecificiteOk, false, '', 'Spécificité correcte');
            const vide = specificiteForm.elements.specificite.value.trim() === '';
            specificiteVide.hidden = !vide;
            btnSpecificiteOk.hidden = vide;
        });
        const enregistrerSpecificite = async () => {
            const { res, data } = await post(apiSpecificite, Object.fromEntries(new FormData(specificiteForm).entries()));
            if (!res.ok) { show(res.status === 422 ? violations(data, 'Spécificité invalide.') : 'Enregistrement impossible.', 'warn'); return false; }
            specificiteDirty = false;
            show('Spécificité enregistrée.');
            return true;
        };
        $('.btn-specificite-enregistrer').addEventListener('click', enregistrerSpecificite);
        btnSpecificiteOk.addEventListener('click', async () => {
            if (specificiteDirty && !(await enregistrerSpecificite())) return;
            const valeur = btnSpecificiteOk.dataset.ok !== '1';
            const { res } = await post(`${apiSpecificiteOk}?valeur=${valeur ? 1 : 0}`);
            if (!res.ok) return show('Action impossible.', 'warn');
            setOk(btnSpecificiteOk, valeur, 'Spécificité validée ✓', 'Spécificité correcte');
        });

        $('.btn-horaire-enregistrer').addEventListener('click', enregistrerHoraire);
        btnHoraireOk.addEventListener('click', async () => {
            if (horaireDirty && !(await enregistrerHoraire())) return;
            const valeur = btnHoraireOk.dataset.ok !== '1';
            const { res } = await post(`${apiHoraireOk}?valeur=${valeur ? 1 : 0}`);
            if (!res.ok) return show('Action impossible.', 'warn');
            setOk(btnHoraireOk, valeur, 'Horaires vérifiés ✓', 'Horaires corrects');
        });

        $('.btn-nom-enregistrer').addEventListener('click', enregistrerNom);
        $('.btn-adresse-enregistrer').addEventListener('click', enregistrerAdresse);
        $('.btn-produits-enregistrer').addEventListener('click', enregistrerProduits);

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
        btnProduitsOk.addEventListener('click', async () => {
            if (produitsDirty && !(await enregistrerProduits())) return;
            const valeur = btnProduitsOk.dataset.ok !== '1';
            const { res } = await post(`${apiProduitsOk}?valeur=${valeur ? 1 : 0}`);
            if (!res.ok) return show('Action impossible.', 'warn');
            setOk(btnProduitsOk, valeur, 'Produits validés ✓', 'Produits corrects');
        });

        // produits : remplace le texte par le contenu du presse-papier (non enregistre tant que l'agent ne le fait pas)
        $('.btn-coller-produits').addEventListener('click', async () => {
            if (!navigator.clipboard || !navigator.clipboard.readText) {
                return show("La lecture du presse-papier n'est pas disponible sur ce navigateur.", 'warn');
            }
            try {
                const texte = (await navigator.clipboard.readText()).trim();
                if (texte === '') return show('Le presse-papier est vide.', 'warn');
                const champ = produitsForm.elements.produits;
                champ.value = texte;
                // declenche le suivi des modifications, la detection des mentions de temps, etc.
                produitsForm.dispatchEvent(new Event('input', { bubbles: true }));
                champ.focus();
            } catch {
                show('Impossible de lire le presse-papier, vérifiez les autorisations du navigateur.', 'warn');
            }
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
        const aller = (url) => window.open(url, '_blank', 'noopener');
        const ouvrir = (base, texte = requete()) => aller(`${base}${encodeURIComponent(texte)}`);
        $('.btn-google').addEventListener('click', () => ouvrir('https://www.google.com/search?q='));
        // mode IA de Google (udm=50)
        $('.btn-google-ia').addEventListener('click', () => ouvrir('https://www.google.com/search?udm=50&q='));
        $('.btn-maps').addEventListener('click', () => ouvrir('https://www.google.com/maps?q='));
        // photos : enseigne, etals, panneau du marche
        $('.btn-images').addEventListener('click', () => ouvrir('https://www.google.com/search?tbm=isch&q='));

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
        return {
            flush: async () => (!nomDirty || await enregistrerNom())
                && (!adresseDirty || await enregistrerAdresse())
                && (!horaireDirty || await enregistrerHoraire())
                && (!produitsDirty || await enregistrerProduits())
                && (!specificiteDirty || await enregistrerSpecificite()),
        };
    };

    const cards = [...document.querySelectorAll('.fiche-card')].map(initCard);

    // ---------------------------------------------------------------- decision garder / ne pas garder
    // une seule decision pour la fiche et son groupe ; recliquer sur le choix actif l'annule
    const libellesGarde = { 1: ['Garder', 'Gardée ✓'], 0: ['Ne pas garder', 'Pas gardée ✓'] };
    const boutonsGarde = [...document.querySelectorAll('.btn-garde')];
    boutonsGarde.forEach((btn) => {
        btn.addEventListener('click', async () => {
            const dejaActif = btn.getAttribute('aria-pressed') === 'true';
            const { res, data } = await post(`${apiGarde}?valeur=${dejaActif ? '' : btn.dataset.valeur}`);
            if (!res.ok) {
                pageMessage.className = 'flash warn';
                pageMessage.textContent = data.message || 'Action impossible.';
                return;
            }
            boutonsGarde.forEach((autre) => {
                const actif = !dejaActif && autre === btn;
                autre.setAttribute('aria-pressed', actif ? 'true' : 'false');
                autre.textContent = libellesGarde[autre.dataset.valeur][actif ? 1 : 0];
            });
        });
    });

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
