// Cases "Tout sélectionner" : data-tout-selectionner="<selecteur du bloc>" coche ou decoche
// toutes les cases du bloc, et suit leur etat (cochee si toutes le sont).
document.querySelectorAll('[data-tout-selectionner]').forEach((tout) => {
    const bloc = document.querySelector(tout.dataset.toutSelectionner);
    const cases = [...bloc.querySelectorAll('input[type="checkbox"]')].filter((c) => c !== tout);
    const suivre = () => {
        const cochees = cases.filter((c) => c.checked).length;
        tout.checked = cochees === cases.length && cases.length > 0;
        tout.indeterminate = cochees > 0 && cochees < cases.length;
    };
    tout.addEventListener('change', () => { cases.forEach((c) => { c.checked = tout.checked; }); suivre(); });
    cases.forEach((c) => c.addEventListener('change', suivre));
    suivre();
});
