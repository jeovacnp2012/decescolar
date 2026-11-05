(function () {
  'use strict';

  /* ---------- Máscaras (LGPD: mostram o dado formatado; o servidor revalida tudo) ---------- */
  var mascaras = {
    cpf: function (v) {
      v = v.replace(/\D/g, '').slice(0, 11);
      return v.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    },
    telefone: function (v) {
      v = v.replace(/\D/g, '').slice(0, 11);
      if (v.length > 10) return v.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
      if (v.length > 6) return v.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
      if (v.length > 2) return v.replace(/(\d{2})(\d{0,5})/, '($1) $2');
      return v ? '(' + v : v;
    },
    cep: function (v) {
      v = v.replace(/\D/g, '').slice(0, 8);
      return v.replace(/(\d{5})(\d)/, '$1-$2');
    }
  };
  document.querySelectorAll('[data-mascara]').forEach(function (el) {
    var f = mascaras[el.dataset.mascara];
    el.addEventListener('input', function () { el.value = f(el.value); });
    if (el.value) el.value = f(el.value);
  });

  /* ---------- Select pesquisável de unidades de saúde ---------- */
  var combo = document.getElementById('combo-unidade');
  var busca = document.getElementById('unidade-busca');
  var lista = document.getElementById('unidade-lista');
  var hidden = document.getElementById('unidade_id');
  var unidades = [];
  try { unidades = JSON.parse(combo.dataset.unidades || '[]'); } catch (e) {}
  var ativo = -1, visiveis = [];

  function norm(s) { return s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }

  function render(filtro) {
    var q = norm(filtro || '');
    visiveis = unidades.filter(function (u) { return norm(u.name).indexOf(q) !== -1; });
    lista.innerHTML = '';
    if (!visiveis.length) {
      var li = document.createElement('li'); li.className = 'vazio'; li.textContent = 'Nenhuma unidade encontrada';
      lista.appendChild(li);
    }
    visiveis.forEach(function (u, i) {
      var li = document.createElement('li');
      li.setAttribute('role', 'option'); li.id = 'unidade-op-' + u.id; li.textContent = u.name;
      li.setAttribute('aria-selected', String(i === ativo));
      li.addEventListener('mousedown', function (e) { e.preventDefault(); escolher(u); });
      li.addEventListener('touchend', function (e) { e.preventDefault(); escolher(u); });
      lista.appendChild(li);
    });
  }
  function abrir() { lista.classList.remove('oculto'); busca.setAttribute('aria-expanded', 'true'); }
  function fechar() { lista.classList.add('oculto'); busca.setAttribute('aria-expanded', 'false'); ativo = -1; }
  function escolher(u) { hidden.value = u.id; busca.value = u.name; fechar(); }

  var atual = unidades.filter(function (u) { return String(u.id) === String(hidden.value); })[0];
  if (atual) busca.value = atual.name;

  busca.addEventListener('focus', function () { render(atual && busca.value === atual.name ? '' : busca.value); abrir(); });
  busca.addEventListener('input', function () { hidden.value = ''; atual = null; ativo = -1; render(busca.value); abrir(); });
  busca.addEventListener('blur', function () { setTimeout(function () {
    fechar();
    if (!hidden.value) busca.value = busca.value; // mantém o texto digitado; o servidor exige uma unidade da lista
  }, 150); });
  busca.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowDown') { e.preventDefault(); abrir(); ativo = Math.min(ativo + 1, visiveis.length - 1); render(busca.value); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); ativo = Math.max(ativo - 1, 0); render(busca.value); }
    else if (e.key === 'Enter') { if (!lista.classList.contains('oculto')) { e.preventDefault(); if (visiveis[ativo >= 0 ? ativo : 0]) escolher(visiveis[ativo >= 0 ? ativo : 0]); } }
    else if (e.key === 'Escape') { fechar(); }
  });

  /* ---------- Checkbox + componente repetível de filhos ---------- */
  var chk = document.getElementById('pedir_atestado');
  var area = document.getElementById('area-filhos');
  var listaFilhos = document.getElementById('lista-filhos');
  var modelo = document.getElementById('modelo-filho');
  var proximo = 0;

  function renumerar() {
    var itens = listaFilhos.querySelectorAll('[data-filho]');
    itens.forEach(function (el, i) {
      el.querySelector('[data-titulo]').textContent = 'Filho(a) ' + (i + 1);
      el.querySelector('[data-remover]').classList.toggle('oculto', itens.length === 1);
    });
  }
  function adicionar() {
    var html = modelo.innerHTML.replace(/__I__/g, String(proximo++));
    var tmp = document.createElement('div'); tmp.innerHTML = html;
    var el = tmp.firstElementChild;
    listaFilhos.appendChild(el);
    renumerar();
    return el;
  }
  listaFilhos.addEventListener('click', function (e) {
    var b = e.target.closest('[data-remover]');
    if (!b) return;
    b.closest('[data-filho]').remove();
    renumerar();
  });
  document.getElementById('add-filho').addEventListener('click', function () {
    var el = adicionar(); el.querySelector('input').focus();
  });

  // Índices já renderizados pelo servidor (reexibição após erro de validação)
  listaFilhos.querySelectorAll('[data-filho] input[name]').forEach(function (inp) {
    var m = inp.name.match(/^filhos\[(\d+)\]/);
    if (m) proximo = Math.max(proximo, parseInt(m[1], 10) + 1);
  });
  renumerar();

  function alternar() {
    if (chk.checked) {
      area.classList.remove('oculto');
      if (!listaFilhos.querySelector('[data-filho]')) { var el = adicionar(); }
    } else {
      area.classList.add('oculto');
    }
  }
  chk.addEventListener('change', function () { alternar(); if (chk.checked) { var i = listaFilhos.querySelector('input'); if (i) i.focus(); } });
  alternar();

  /* ---------- Envio: evita duplo clique ---------- */
  var form = document.getElementById('form-pedido');
  form.addEventListener('submit', function () {
    var b = document.getElementById('enviar'); b.disabled = true; b.textContent = 'Enviando…';
    // Campos de filhos ocultos não devem ser enviados se o checkbox não está marcado
    if (!chk.checked) listaFilhos.innerHTML = '';
  });
})();
