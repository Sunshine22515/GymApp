// Confirma as ações destrutivas do painel antes de as enviar.
document.querySelectorAll('form[data-confirm]').forEach(form => {
  form.addEventListener('submit', event => {
    const message = form.dataset.confirm || 'Confirmas esta ação?';
    if (!window.confirm(message)) event.preventDefault();
  });
});

// O service worker guarda apenas recursos estáticos, nunca páginas privadas.
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('./sw.js').catch(error => {
      console.info('A app continua funcional sem o service worker.', error);
    });
  });
}
