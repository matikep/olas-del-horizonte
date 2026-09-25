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
