// Formateo automático de campos (el servidor valida igual; esto solo evita errores al escribir).

// Celular: solo 8 dígitos después del "+56 9" fijo. Si pegan +56 9… o 9…, se quita el prefijo.
document.querySelectorAll('.tel input').forEach(i => i.addEventListener('input', () => {
  let d = i.value.replace(/\D/g, '');
  if (d.length >= 11 && d.startsWith('569')) d = d.slice(3);
  else if (d.length === 9 && d.startsWith('9')) d = d.slice(1);
  d = d.slice(0, 8);
  i.value = d.length > 4 ? d.slice(0, 4) + ' ' + d.slice(4) : d;
}));

// RUT: pone puntos y guion solo ("12345678k" -> "12.345.678-K") y revisa el dígito verificador.
function rutValido(rut) {
  const c = rut.replace(/[^0-9K]/gi, '').toUpperCase();
  const cuerpo = c.slice(0, -1), dv = c.slice(-1);
  if (!/^\d{6,8}$/.test(cuerpo)) return false;
  let suma = 0, mul = 2;
  for (let i = cuerpo.length - 1; i >= 0; i--) {
    suma += Number(cuerpo[i]) * mul;
    mul = mul === 7 ? 2 : mul + 1;
  }
  const r = 11 - (suma % 11);
  return dv === (r === 11 ? '0' : r === 10 ? 'K' : String(r));
}

function rutFormato(valor) {
  let v = valor.replace(/[^0-9kK]/g, '').toUpperCase().slice(0, 9);
  if (v.length < 2) return v;
  const cuerpo = v.slice(0, -1).replace(/K/g, ''), dv = v.slice(-1);
  return cuerpo.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + '-' + dv;
}

document.querySelectorAll('input.rut').forEach(i => {
  const revisar = () => i.setCustomValidity(i.value && !rutValido(i.value) ? 'RUT inválido: revisa el número y el dígito verificador.' : '');
  i.addEventListener('input', () => { i.value = rutFormato(i.value); revisar(); });
  revisar();
});

// Buscador de tablas: filtra filas en vivo. Ignora tildes y mayúsculas; RUT y teléfonos se encuentran
// escritos con o sin puntos, guion o espacios. Busca también en data-buscar (datos que no se ven en la tabla).
const sinTildes = t => t.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
const soloDigitos = t => t.replace(/[^0-9k]/gi, '').toLowerCase();
document.querySelectorAll('input[data-filtra]').forEach(input => {
  const tabla = document.querySelector(input.dataset.filtra);
  if (!tabla || !tabla.tBodies[0]) return;
  const filas = [...tabla.tBodies[0].rows];
  const indice = filas.map(tr => {
    const texto = tr.textContent + ' ' + (tr.dataset.buscar || '');
    return { texto: sinTildes(texto), digitos: soloDigitos(texto) };
  });
  const contador = input.parentElement.querySelector('.buscador-n');
  const vacia = tabla.tBodies[0].insertRow();
  vacia.hidden = true;
  vacia.className = 'sin-resultados';
  const celda = vacia.insertCell();
  celda.colSpan = filas[0] ? filas[0].cells.length : 1;
  celda.textContent = 'No hay resultados para tu búsqueda.';

  const filtrar = () => {
    const q = sinTildes(input.value.trim());
    const palabras = q.split(/\s+/).filter(Boolean);
    const esNumero = /^[0-9k.\-\s+]+$/i.test(q) && soloDigitos(q).length >= 3;
    let visibles = 0;
    filas.forEach((tr, i) => {
      const coincide = !q || palabras.every(p => indice[i].texto.includes(p))
        || (esNumero && indice[i].digitos.includes(soloDigitos(q)));
      tr.hidden = !coincide;
      if (coincide) visibles++;
    });
    vacia.hidden = !q || visibles > 0;
    contador.textContent = q ? visibles + (visibles === 1 ? ' resultado' : ' resultados') : '';
  };
  input.addEventListener('input', filtrar);
  if (input.value) filtrar();   // búsqueda que viene desde el menú (?q=)
});
