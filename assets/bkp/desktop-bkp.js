// assets/desktop.js – Navegación SPA y helpers del nuevo panel

(function(){
  const sections = document.querySelectorAll('.panel-section');
  const links = document.querySelectorAll('.sidebar li');

  function show(id){
    sections.forEach(s=>s.classList.toggle('active', s.id === 'section-'+id));
    links.forEach(l=>l.classList.toggle('active', l.dataset.section === id));
  }

  links.forEach(l=>{
    l.addEventListener('click', ()=>{
      show(l.dataset.section);
      history.pushState({},'', '#'+l.dataset.section);
    });
  });

  // On load – open hash or first
  const hash = location.hash.replace('#','');
  show(hash || links[0].dataset.section);

  // Reemplazar alert por notificaciones con auto-desaparición
  window.alert = function(message){
    const text = String(message);
    const type = /error|falló|falla|incorrect|vencid|vencida/i.test(text) ? 'error'
               : (/guardad|renovad|generad|cambiad|ok|éxito|hecho/i.test(text) ? 'success' : 'info');
    NotificationSystem.show(text, type);
  };


  // Simple fetch helper with CSRF
  window.$post = async function(url, data){
    data = Object.assign({csrf_token: CSRF_TOKEN}, data);
    const res = await fetch(url,{method:'POST',body:new URLSearchParams(data)});
    return res.json();
  };
  // --- License forms ---
  const formRenovar = document.getElementById('form-renovar');
  if(formRenovar){
    formRenovar.addEventListener('submit', async e => {
      e.preventDefault();
      const data = Object.fromEntries(new FormData(formRenovar));
      try {
        const res = await $post('desktop.php', data);
        if(res.ok){
          alert('Licencia renovada');
          updateLicSummary(res.lic);
        } else alert(res.msg||'Error');
      }catch(err){ alert('Error'); }
    });
  }
  const formManual = document.getElementById('form-manual');
  if(formManual){
    formManual.addEventListener('submit', async e => {
      e.preventDefault();
      const data = Object.fromEntries(new FormData(formManual));
      try{
        const res = await $post('desktop.php', data);
        if(res.ok){
          alert('Datos guardados');
          updateLicSummary(res.lic);
        } else alert(res.msg||'Error');
      }catch(err){ alert('Error'); }
    });
  }
  function updateLicSummary(lic){
    const el = document.getElementById('lic-summary');
    if(!el||!lic)return;
    if(lic.expired){
      el.innerHTML = `<strong style="color:#dc2626;">Licencia vencida hace ${lic.days_expired} días</strong>`;
    }else{
      el.innerHTML = `Vigente – quedan <strong>${lic.days_remaining}</strong> días (expira el ${lic.end_date})`;
    }
    document.querySelector('.lic-status').textContent = lic.expired ? 'Licencia vencida' : 'Licencia vigente · '+lic.days_remaining+' días';
    document.querySelector('.lic-status').className = 'lic-status ' + (lic.expired?'error':'ok');
  }
  // --- Tabs toggle ---
  const tabButtons = document.querySelectorAll('.tabs-inline button');
  tabButtons.forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const tab = btn.dataset.tab;
      // buttons
      tabButtons.forEach(b=>b.classList.toggle('active', b===btn));
      // contents
      document.querySelectorAll('#section-rest .tab-content').forEach(c=>c.classList.toggle('active', c.id==='tab-'+tab));
    });
  });

  // --- Categories ---
  const catList = document.getElementById('cat-list');
  if(catList){
    let dragSrc;
    catList.addEventListener('dragstart', e=>{
      dragSrc = e.target.closest('.cat-item');
      e.dataTransfer.effectAllowed='move';
    });
    catList.addEventListener('dragover', e=>{
      e.preventDefault();
      const over = e.target.closest('.cat-item');
      if(!over||over===dragSrc)return;
      const rect = over.getBoundingClientRect();
      const next = (e.clientY - rect.top)/(rect.bottom-rect.top) > .5;
      catList.insertBefore(dragSrc, next? over.nextSibling : over);
    });

    // delete
    catList.addEventListener('click', async e=>{
      if(e.target.classList.contains('del-cat')){
        const li = e.target.closest('.cat-item');
        if(confirm('Eliminar categoría?')){
          const id = li.dataset.id;
          const res = await $post('desktop.php',{accion:'eliminar_categoria',cat_id:id});
          if(res.ok) li.remove();
        }
      }
    });
  }

  const formCatAdd = document.getElementById('form-cat-add');
  if(formCatAdd){
    formCatAdd.addEventListener('submit', async e=>{
      e.preventDefault();
      const data = Object.fromEntries(new FormData(formCatAdd));
      const res = await $post('desktop.php',data);
      if(res.ok){
        location.reload();
      }else alert('Error');
    });
  }
  const btnCatSave = document.getElementById('btn-cat-save');
  if(btnCatSave){
    btnCatSave.addEventListener('click', async ()=>{
      const ids=[]; const nombres=[];
      catList.querySelectorAll('.cat-item').forEach(li=>{
        ids.push(li.dataset.id);
        nombres.push(li.querySelector('input').value.trim());
      });
      const res = await $post('desktop.php',{accion:'guardar_categorias',orden:ids.join(','),nombres:nombres.join('|')});
      alert(res.ok?'Categorias guardadas':'Error');
    });
  }

  // --- Dishes ---
  const dishLists = document.querySelectorAll('.dish-list');
  dishLists.forEach(list=>{
    let dragSrc;
    list.addEventListener('dragstart', e=>{
      dragSrc = e.target.closest('.dish-item');
      e.dataTransfer.effectAllowed='move';
    });
    list.addEventListener('dragover', e=>{
      e.preventDefault();
      const over = e.target.closest('.dish-item');
      if(!over||over===dragSrc)return;
      const rect = over.getBoundingClientRect();
      const next = (e.clientY-rect.top)/(rect.height)>.5;
      list.insertBefore(dragSrc, next? over.nextSibling: over);
    });
    // delete
    list.addEventListener('click', async e=>{
      if(e.target.classList.contains('del-dish')){
        const li=e.target.closest('.dish-item');
        if(confirm('Eliminar plato?')){
          const id=li.dataset.id;
          const res=await $post('desktop.php',{accion:'eliminar_plato',plato_id:id});
          if(res.ok) li.remove();
        }
      } else if(e.target.classList.contains('btn-img')){
        const li=e.target.closest('.dish-item');
        const id=li.dataset.id;
        const fileInput=document.createElement('input');
        fileInput.type='file';
        fileInput.accept='image/*';
        fileInput.onchange=async ()=>{
          if(!fileInput.files[0]) return;
          const fd=new FormData();
          fd.append('accion','cambiar_img_plato');
          fd.append('plato_id',id);
          fd.append('imagen',fileInput.files[0]);
          fd.append('csrf_token',CSRF_TOKEN);
          const res=await fetch('desktop.php',{method:'POST',body:fd});
          const j=await res.json();
          if(j.ok){ li.querySelector('img.dish-thumb').src=j.path+'?'+Date.now(); }
        };
        fileInput.click();
      }
    });
  });

  // add dish forms
  document.querySelectorAll('.form-dish-add').forEach(f=>{
    f.addEventListener('submit', async e=>{
      e.preventDefault();
      const data=Object.fromEntries(new FormData(f));
      const res=await $post('desktop.php',data);
      if(res.ok) location.reload();
    });
  });

  const btnDishSave=document.getElementById('btn-dish-save');
  if(btnDishSave){
    btnDishSave.addEventListener('click', async ()=>{
      const ids=[]; const names=[]; const prices=[]; const descs=[]; const ordenes={};
      dishLists.forEach(list=>{
        const catId=list.id.replace('dish-list-','');
        const order=[];
        list.querySelectorAll('.dish-item').forEach(li=>{
          const id=li.dataset.id;
          ids.push(id);
          names.push(li.querySelector('.dish-name').value.trim());
          descs.push(li.querySelector('.dish-desc').value.trim());
          prices.push(li.querySelector('.dish-price').value);
          order.push(id);
        });
        ordenes[catId]=order.join(',');
      });
      const fd=new FormData();
      fd.append('accion','guardar_platos');
      ids.forEach((id,i)=>{
        fd.append('plato_id[]',id);
        fd.append('nombre[]',names[i]);
        fd.append('descripcion[]',descs[i]);
        fd.append('precio[]',prices[i]);
      });
      for(const cid in ordenes){ fd.append(`orden_platos[${cid}]`, ordenes[cid]); }
      fd.append('csrf_token',CSRF_TOKEN);
      const res=await fetch('desktop.php',{method:'POST',body:fd}).then(r=>r.json());
      alert(res.ok?'Platos guardados':'Error');
    });
  }

  // --- Footer form ---
  const formFooter=document.getElementById('form-footer');
  if(formFooter){
    formFooter.addEventListener('submit',async e=>{
      e.preventDefault();
      const data=Object.fromEntries(new FormData(formFooter));
      const res=await $post('desktop.php',data);
      alert(res.ok?'Footer guardado':'Error');
    });
  }

  // --- Theme form ---
  const formTheme=document.getElementById('form-theme');
  if(formTheme){
    formTheme.addEventListener('submit', async e=>{
      e.preventDefault();
      const data=Object.fromEntries(new FormData(formTheme));
      const res=await $post('desktop.php',data);
      alert(res.ok?'Tema guardado':'Error');
    });
  }

  // --- Users ---
  const formUserAdd=document.getElementById('form-user-add');
  if(formUserAdd){
    formUserAdd.addEventListener('submit',async e=>{
      e.preventDefault();
      const data=Object.fromEntries(new FormData(formUserAdd));
      const res=await $post('desktop.php',data);
      if(res.ok) location.reload(); else alert('Error');
    });
  }
  document.querySelectorAll('.user-table').forEach(tbl=>{
    tbl.addEventListener('click',async e=>{
      const tr=e.target.closest('tr');
      if(!tr) return;
      const uid=tr.dataset.id;
      if(e.target.classList.contains('btn-del')){
        if(confirm('Eliminar usuario?')){
          const res=await $post('desktop.php',{accion:'eliminar_usuario',uid});
          if(res.ok) tr.remove();
        }
      }
      if(e.target.classList.contains('btn-pass')){
        const pwd=prompt('Nueva clave (8+)');
        if(pwd && pwd.length>=8){
          const res=await $post('desktop.php',{accion:'cambiar_pass',uid,clave:pwd});
          alert(res.ok?'Clave cambiada':'Error');
        }
      }
    });
  });

  // --- Form Restaurant ---
  const formRest = document.getElementById('form-rest');
  if(formRest){
    formRest.addEventListener('submit', async e=>{
      e.preventDefault();
      const fd = new FormData(formRest);
      fd.append('csrf_token', CSRF_TOKEN);
      try{
        const res = await fetch('desktop.php',{method:'POST',body:fd});
        const j = await res.json();
        alert(j.ok ? 'Datos guardados' : 'Error');
      }catch(err){ alert('Error'); }
    });
  }

  const formSeo = document.getElementById('form-seo');
  if(formSeo){
    formSeo.addEventListener('submit', async e=>{
      e.preventDefault();
      const fd = new FormData(formSeo);
      fd.append('csrf_token', CSRF_TOKEN);
      try{
        const res = await fetch('desktop.php',{method:'POST',body:fd});
        const j = await res.json();
        alert(j.ok ? 'SEO guardado' : 'Error');
      }catch(err){ alert('Error'); }
    });
  }
  const btnSitemap=document.getElementById('btn-sitemap');
  if(btnSitemap){
    btnSitemap.addEventListener('click', async ()=>{
      const res = await $post('desktop.php',{accion:'generar_sitemap'});
      alert(res.ok?'Sitemap generado':'Error');
      if(res.ok) location.reload();
    });
  }

  // --- SEO Check ---
  const btnSeoCheck = document.getElementById('btn-seo-check');
  if(btnSeoCheck){
    btnSeoCheck.addEventListener('click', async ()=>{
      const res = await $post('desktop.php',{accion:'seo_check'});
      if(!res.ok){ alert('Error en verificación'); return; }
      const reportDiv = document.getElementById('seo-report');
      reportDiv.innerHTML='';
        const banner=document.createElement('div');
        banner.className= res.overall==='OK' ? 'seo-banner-ok' : (res.overall==='WARNING' ? 'seo-banner-warn' : 'seo-banner-err');
        banner.textContent = res.overall==='OK' ? '✔ El contenido SEO está completo' : '✖ El contenido SEO NO está completo, revise los puntos marcados.';
        reportDiv.appendChild(banner);
      const tbl = document.createElement('table');
      tbl.className = 'seo-table';
      tbl.innerHTML = '<tr><th>Elemento</th><th>Estado</th><th>Detalle</th><th>Valor</th></tr>';
      (res.rows||[]).forEach(r=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${r[0]}</td><td>${r[1]}</td><td>${r[2]}</td><td>${r[3]||''}</td>`;
        tr.className = r[1]==='ERROR' ? 'seo-err' : (r[1]==='WARNING' ? 'seo-warn' : 'seo-ok');
        tbl.appendChild(tr);
      });
      reportDiv.appendChild(tbl);
      const btnCsv = document.getElementById('btn-seo-download');
      if(btnCsv){
        btnCsv.style.display='inline-block';
        btnCsv.onclick = ()=>{
          const link = document.createElement('a');
          link.download = 'seo_report.csv';
          link.href = 'data:text/csv;base64,' + res.csv;
          link.click();
        };
      }
    });
  }
})();
