// scripts.js - Funciones JS para menú digital
// Resumen con propina, limpiar pedido, WhatsApp, cache localStorage/IndexedDB

// Resumen de pedido, propina, limpiar, WhatsApp, cache local
function calcularResumen() {
  let total = 0;
  document.querySelectorAll('.plato input[type=number]').forEach(inp => {
    total += (parseInt(inp.value)||0) * parseInt(inp.dataset.precio||0);
  });
  let propina = Math.round(total * 0.10);
  if(document.getElementById('resumen-total'))
    document.getElementById('resumen-total').textContent = '$' + total.toLocaleString('es-CL');
  if(document.getElementById('resumen-propina'))
    document.getElementById('resumen-propina').textContent = '$' + propina.toLocaleString('es-CL');
  if(document.getElementById('resumen-final'))
    document.getElementById('resumen-final').textContent = '$' + (total+propina).toLocaleString('es-CL');
}
function limpiarPedido() {
  document.querySelectorAll('.plato input[type=number]').forEach(inp => {
    inp.value = 0;
    let key = 'plato_' + inp.name;
    localStorage.setItem(key, 0);
  });
  calcularResumen();
}
function enviarWhatsApp() {
  let pedido = [];
  document.querySelectorAll('.plato').forEach(plato => {
    let cant = plato.querySelector('input[type=number]')?.value;
    if (cant > 0) pedido.push(cant + 'x ' + plato.querySelector('h3').textContent);
  });
  let total = document.getElementById('resumen-final')?.textContent;
  let msg = encodeURIComponent('Hola! Quiero pedir: ' + pedido.join(', ') + '. Total: ' + total);
  window.open('https://wa.me/56945787874?text=' + msg, '_blank');
}
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.plato input[type=number]').forEach(inp => {
    let key = 'plato_' + inp.name;
    inp.value = localStorage.getItem(key) || 0;
    inp.addEventListener('input', function() {
      localStorage.setItem(key, inp.value);
      calcularResumen();
    });
  });
  calcularResumen();
});

// Botón limpiar pedido (ejemplo, requiere integración con tu lógica de pedido)
function limpiarPedido() {
    document.querySelectorAll('.plato input[type=number]').forEach(inp => {
    inp.value = 0;
    let key = 'plato_' + inp.name;
    localStorage.setItem(key, 0);
  });
  calcularResumen();
}

// Calcular total con propina 10%
function calcularTotalConPropina(total) {
    return Math.round(total * 1.1);
}

// Botón WhatsApp (ejemplo de función)
function enviarPedidoWhatsApp(numero, texto) {
    var url = 'https://wa.me/' + encodeURIComponent(numero) + '?text=' + encodeURIComponent(texto);
    window.open(url, '_blank');
}

// Lazy load para imágenes (opcional, si quieres soporte extra)
document.addEventListener('DOMContentLoaded', function() {
    if ('loading' in HTMLImageElement.prototype) return; // Nativo
    var imgs = document.querySelectorAll('img[loading="lazy"]');
    imgs.forEach(function(img) {
        if (img.dataset.src) img.src = img.dataset.src;
    });
});

// Puedes agregar aquí más funciones para drag & drop, cache, etc.
