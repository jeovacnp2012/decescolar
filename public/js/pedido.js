(function () {
  'use strict';

  /* ---------- Máscaras (LGPD: mostram o dado formatado; o servidor revalida tudo) ---------- */
  function mascaraCnpj(v) {
    v = v.toUpperCase().replace(/[^0-9A-Z]/g, '').slice(0, 14); // CNPJ novo pode ter letras
    return v.replace(/^(.{2})(.)/, '$1.$2').replace(/^(.{2}\..{3})(.)/, '$1.$2')
      .replace(/^(.{2}\..{3}\..{3})(.)/, '$1/$2').replace(/^(.{2}\..{3}\..{3}\/.{4})(.)/, '$1-$2');
  }
  function mascaraCpf(v) {
    v = v.replace(/\D/g, '').slice(0, 11);
    return v.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
  }
  var mascaras = {
    cpf: mascaraCpf,
    cnpj: mascaraCnpj,
    // consulta: serve para CPF ou CNPJ
    doc: function (v) {
      var limpo = v.toUpperCase().replace(/[^0-9A-Z]/g, '');
      return /^\d{0,11}$/.test(limpo) ? mascaraCpf(limpo) : mascaraCnpj(limpo);
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
  function aplicarMascara(el) {
    var f = mascaras[el.dataset.mascara];
    if (f && el.value) el.value = f(el.value);
  }
  // delegação: vale também para campos criados depois (filhos / funcionários)
  document.addEventListener('input', function (e) {
    var f = e.target.dataset && mascaras[e.target.dataset.mascara];
    if (f) e.target.value = f(e.target.value);
  });
  document.querySelectorAll('[data-mascara]').forEach(aplicarMascara);

  var form = document.getElementById('form-pedido');
  if (!form) return; // tela de consulta: só as máscaras

  /* ---------- Select pesquisável de unidades de saúde (um por campo) ---------- */
  var unidades = [];
  try { unidades = JSON.parse(document.getElementById('dados-unidades').textContent || '[]'); } catch (e) {}

  function norm(s) { return s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }

  function iniciarCombo(combo) {
    var busca = combo.querySelector('.combo-busca');
    var lista = combo.querySelector('.combo-lista');
    var hidden = combo.querySelector('.combo-valor');
    var ativo = -1, visiveis = [];

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
        li.setAttribute('role', 'option'); li.textContent = u.name;
        li.setAttribute('aria-selected', String(i === ativo));
        // mousedown só impede o campo de perder o foco; a escolha é no click (não dispara ao rolar a lista com o dedo)
        li.addEventListener('mousedown', function (e) { e.preventDefault(); });
        li.addEventListener('click', function () { escolher(u); });
        lista.appendChild(li);
      });
    }
    function abrir() { lista.classList.remove('oculto'); busca.setAttribute('aria-expanded', 'true'); }
    function fechar() { lista.classList.add('oculto'); busca.setAttribute('aria-expanded', 'false'); ativo = -1; }
    function escolher(u) { hidden.value = u.id; busca.value = u.name; atual = u; fechar(); }

    var atual = unidades.filter(function (u) { return String(u.id) === String(hidden.value); })[0];
    if (atual) busca.value = atual.name;

    busca.addEventListener('focus', function () {
      render(atual && busca.value === atual.name ? '' : busca.value); abrir();
      // No celular o teclado cobre a metade de baixo: sobe o campo para o topo, deixando a lista visível.
      setTimeout(function () { combo.scrollIntoView({ block: 'start', behavior: 'smooth' }); }, 300);
    });
    busca.addEventListener('input', function () { hidden.value = ''; atual = null; ativo = -1; render(busca.value); abrir(); });
    busca.addEventListener('blur', function () { setTimeout(fechar, 150); });
    busca.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); abrir(); ativo = Math.min(ativo + 1, visiveis.length - 1); render(busca.value); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); ativo = Math.max(ativo - 1, 0); render(busca.value); }
      else if (e.key === 'Enter') { if (!lista.classList.contains('oculto')) { e.preventDefault(); if (visiveis[ativo >= 0 ? ativo : 0]) escolher(visiveis[ativo >= 0 ? ativo : 0]); } }
      else if (e.key === 'Escape') { fechar(); }
    });
  }
  document.querySelectorAll('[data-combo]').forEach(iniciarCombo);

  /* ---------- Componente repetível (filhos / funcionários) ---------- */
  function repetidor(idLista, idModelo, idBotao, prefixo, rotulo) {
    var lista = document.getElementById(idLista);
    var modelo = document.getElementById(idModelo);
    var proximo = 0;

    function renumerar() {
      var itens = lista.querySelectorAll('[data-item]');
      itens.forEach(function (el, i) {
        el.querySelector('[data-titulo]').textContent = rotulo + ' ' + (i + 1);
        el.querySelector('[data-remover]').classList.toggle('oculto', itens.length === 1);
      });
    }
    function adicionar() {
      var tmp = document.createElement('div');
      tmp.innerHTML = modelo.innerHTML.replace(/__I__/g, String(proximo++));
      var el = tmp.firstElementChild;
      lista.appendChild(el);
      el.querySelectorAll('[data-combo]').forEach(iniciarCombo);
      if (!unidades.length) el.querySelectorAll('.combo-busca').forEach(function (i) { i.disabled = true; });
      renumerar();
      return el;
    }
    lista.addEventListener('click', function (e) {
      var b = e.target.closest('[data-remover]');
      if (!b) return;
      b.closest('[data-item]').remove();
      renumerar();
    });
    document.getElementById(idBotao).addEventListener('click', function () {
      var el = adicionar(); var i = el.querySelector('input'); if (i) i.focus();
    });
    // Índices já renderizados pelo servidor (reexibição após erro de validação)
    var re = new RegExp('^' + prefixo + '\\[(\\d+)\\]');
    lista.querySelectorAll('[data-item] [name]').forEach(function (inp) {
      var m = inp.name.match(re);
      if (m) proximo = Math.max(proximo, parseInt(m[1], 10) + 1);
    });
    renumerar();

    return { lista: lista, garantirUm: function () { if (!lista.querySelector('[data-item]')) adicionar(); } };
  }
  var filhos = repetidor('lista-filhos', 'modelo-filho', 'add-filho', 'filhos', 'Filho(a)');
  var funcionarios = repetidor('lista-funcionarios', 'modelo-funcionario', 'add-funcionario', 'funcionarios', 'Funcionário');

  /* ---------- Tipo de interessado: mostra só o que vale para a escolha ---------- */
  var selTipo = document.getElementById('tipo');
  var corpo = document.getElementById('corpo');
  var inpNome = document.getElementById('nome');

  function aplicarTipo() {
    var tipo = selTipo.value;
    corpo.classList.toggle('oculto', !tipo);

    document.querySelectorAll('[data-tipos]').forEach(function (el) {
      var mostra = !!tipo && el.dataset.tipos.split(' ').indexOf(tipo) !== -1;
      el.classList.toggle('oculto', !mostra);
      // campos ocultos não são enviados
      el.querySelectorAll('input, select, textarea').forEach(function (i) { i.disabled = !mostra; });
    });
    document.querySelectorAll('[data-por-tipo]').forEach(function (el) {
      var mapa = el._mapa || (el._mapa = JSON.parse(el.getAttribute('data-por-tipo')));
      el.textContent = mapa[tipo] || '';
    });
    if (!unidades.length) document.querySelectorAll('.combo-busca').forEach(function (i) { i.disabled = true; });

    if (tipo === 'filhos' || tipo === 'responsavel_legal') filhos.garantirUm();
    if (tipo === 'empresa') funcionarios.garantirUm();
    atualizarMae();
  }

  /* ---------- Pai/mãe: se for mãe, o nome dela entra (e trava) no nome da mãe dos filhos ---------- */
  function vinculo() { var r = document.querySelector('input[name="vinculo"]:checked'); return r ? r.value : ''; }
  function atualizarMae() {
    var ehMae = selTipo.value === 'filhos' && vinculo() === 'mae';
    filhos.lista.querySelectorAll('[data-nome-mae]').forEach(function (inp) {
      var dica = inp.parentNode.querySelector('[data-dica-mae]');
      if (ehMae) {
        inp.value = inpNome.value; inp.readOnly = true;
        if (dica) dica.classList.remove('oculto');
      } else if (inp.readOnly) {
        inp.readOnly = false; inp.value = '';
        if (dica) dica.classList.add('oculto');
      }
    });
  }
  selTipo.addEventListener('change', function () { aplicarTipo(); });
  document.querySelectorAll('input[name="vinculo"]').forEach(function (r) { r.addEventListener('change', atualizarMae); });
  inpNome.addEventListener('input', atualizarMae);
  new MutationObserver(atualizarMae).observe(filhos.lista, { childList: true });

  /* ---------- CEP → endereço (ViaCEP, consultado pelo servidor) ---------- */
  var campoCep = document.getElementById('cep');
  var statusCep = document.getElementById('cep-status');
  var textoPadraoCep = statusCep.textContent;
  var ultimoCep = '';
  var camposEnd = ['logradouro', 'bairro', 'cidade', 'uf'].map(function (id) { return document.getElementById(id); });
  camposEnd.forEach(function (el) { el.addEventListener('input', function () { delete el.dataset.auto; }); });

  function preencher(id, valor) {
    var el = document.getElementById(id);
    if (valor && (el.value === '' || el.dataset.auto === '1')) { el.value = valor; el.dataset.auto = '1'; }
  }
  function buscarCep() {
    var d = campoCep.value.replace(/\D/g, '');
    if (d.length !== 8) { ultimoCep = ''; statusCep.textContent = textoPadraoCep; return; }
    if (d === ultimoCep) return;
    ultimoCep = d;
    statusCep.textContent = 'Buscando o endereço…';
    fetch(form.dataset.cepUrl + '/' + d, { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j.encontrado) {
          preencher('logradouro', j.logradouro); preencher('bairro', j.bairro);
          preencher('cidade', j.cidade); preencher('uf', j.uf);
          statusCep.textContent = 'Endereço encontrado. Confira e complete o que faltar.';
          var falta = camposEnd.concat([document.getElementById('numero')]).filter(function (el) { return !el.value; })[0];
          if (falta) falta.focus();
        } else {
          statusCep.textContent = j.indisponivel
            ? 'Não foi possível buscar o endereço agora. Preencha os campos abaixo.'
            : 'CEP não encontrado. Preencha o endereço manualmente.';
        }
      })
      .catch(function () { statusCep.textContent = 'Não foi possível buscar o endereço agora. Preencha os campos abaixo.'; });
  }
  campoCep.addEventListener('input', buscarCep);

  /* ---------- Envio: evita duplo clique ---------- */
  form.addEventListener('submit', function () {
    var b = document.getElementById('enviar'); b.disabled = true; b.textContent = 'Enviando…';
  });

  aplicarTipo();
})();
