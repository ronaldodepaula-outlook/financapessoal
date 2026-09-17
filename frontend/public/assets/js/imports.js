import {$,api,escape,money,date,label,badge,lookups,prepareLookups,fieldHtml,readForm,confirmAction,toast,pagination,empty,formErrors} from './core.js';

const DATE_FORMATS=[['d/m/Y','Dia/mês/ano'],['m/d/Y','Mês/dia/ano'],['Y-m-d','Ano-mês-dia'],['OFX','Automático (OFX)']];
const NUMBER_FORMATS=[['pt-BR','1.234,56 (padrão brasileiro)'],['en-US','1,234.56 (ponto decimal)']];
const METHODS=['PIX','DINHEIRO','DEBITO','TRANSFERENCIA','BOLETO','OUTROS'];
const ROW_STATUS_HELP={INVALIDA:'Revise os dados desta linha antes de confirmar.',DUPLICADA:'Já existe um lançamento equivalente; desmarcada para não duplicar.',PENDENTE:'Pronta para ser importada.',IMPORTADA:'Já gravada como lançamento.'};

export async function mountImports(){
  await prepareLookups();
  $('#page-actions').innerHTML='';
  const selected=new URLSearchParams(location.search).get('record');
  if(selected&&/^\d+$/.test(selected))return renderReview(Number(selected));
  return renderList();
}

function uploadPanel(){
  return `<section class="panel panel-pad">
    <div class="panel-header"><div><h2>Nova importação</h2><p>Envie um extrato CSV ou OFX de até 2&nbsp;MB. Escolha a conta ou o cartão correspondente, exclusivamente.</p></div></div>
    <form id="import-upload" class="import-drop">
      <input type="file" id="import-file" name="file" accept=".csv,.ofx" required>
      <div class="form-grid" style="margin-top:16px;text-align:left">${fieldHtml({key:'account_id',label:'Conta',type:'select',lookup:'accounts'},{})}${fieldHtml({key:'card_id',label:'Cartão',type:'select',lookup:'cards'},{})}</div>
      <div class="modal-actions" style="justify-content:flex-start;border-top:0;padding-top:6px;margin-top:10px"><button class="button primary" type="submit">Enviar arquivo</button></div>
    </form>
  </section>`;
}

function bindUpload(){
  const form=$('#import-upload');
  form.elements.account_id.onchange=()=>{if(form.elements.account_id.value)form.elements.card_id.value='';};
  form.elements.card_id.onchange=()=>{if(form.elements.card_id.value)form.elements.account_id.value='';};
  form.onsubmit=async event=>{
    event.preventDefault();const button=$('button[type=submit]',form);button.disabled=true;
    try{
      const file=$('#import-file',form).files[0];
      if(!file)throw new Error('Selecione um arquivo CSV ou OFX.');
      const accountId=form.elements.account_id.value,cardId=form.elements.card_id.value;
      if(!accountId&&!cardId)throw new Error('Escolha uma conta ou um cartão antes de enviar o arquivo.');
      if(accountId&&cardId)throw new Error('Escolha apenas uma conta OU um cartão, não os dois.');
      const data=new FormData();data.append('file',file);
      if(accountId)data.append('account_id',accountId);if(cardId)data.append('card_id',cardId);
      const record=await api('/imports','POST',data);
      toast('Arquivo recebido. Revise antes de confirmar.');
      history.pushState(null,'','?page=imports&record='+record.id);
      await renderReview(record.id);
    }catch(error){formErrors(form,error);}
    finally{button.disabled=false;}
  };
}

async function renderList(page=1){
  $('#page-content').innerHTML=uploadPanel()+'<section class="panel" style="margin-top:24px"><div id="imports-list"></div></section>';
  bindUpload();
  const result=await api(`/imports?per_page=15&page=${page}`);
  const rows=result.items;
  $('#imports-list').innerHTML=rows.length?`<div class="table-scroll"><table><thead><tr><th>Arquivo</th><th>Formato</th><th>Situação</th><th>Linhas</th><th>Enviado em</th><th class="actions-heading">Ações</th></tr></thead><tbody>${rows.map(r=>`<tr><td class="primary-cell">${escape(r.original_filename)}</td><td>${escape(r.format)}</td><td>${badge(r.status)}</td><td>${r.counts.imported}/${r.counts.total} importadas${r.counts.invalid?` · ${r.counts.invalid} com erro`:''}${r.counts.duplicates?` · ${r.counts.duplicates} duplicadas`:''}${r.counts.left_out?` · ${r.counts.left_out} não selecionadas`:''}</td><td>${date(r.created_at)}</td><td><div class="row-actions"><button class="text-button" data-open="${r.id}">${r.status==='PREVIA'||r.counts.left_out?'Revisar':'Ver detalhes'}</button></div></td></tr>`).join('')}</tbody></table></div>${pagination(result.pagination)}`:empty('Nenhuma importação ainda','Envie um extrato CSV ou OFX para começar a importar seus lançamentos.');
  $('#imports-list').onclick=async event=>{
    const openBtn=event.target.closest('[data-open]');if(openBtn){history.pushState(null,'','?page=imports&record='+openBtn.dataset.open);await renderReview(Number(openBtn.dataset.open));return;}
    const pageBtn=event.target.closest('[data-page]');if(pageBtn)await renderList(Number(pageBtn.dataset.page));
  };
}

function mappingFields(imp){
  const columns=imp.columns.map(c=>[c,c]);const isCard=Boolean(imp.card_id);
  const typeHelp=isCard
    ?'Um cartão só registra despesas neste sistema. Sem coluna de tipo, compras (valor positivo) viram despesa e pagamentos/créditos no cartão (valor negativo) ficam desmarcados para revisão — o pagamento da fatura é registrado em Faturas → Pagamentos.'
    :'Sem seleção, o tipo é definido pelo sinal do valor: negativo vira despesa, positivo vira receita.';
  const fields=[
    {key:'date',label:'Coluna da data',type:'select',options:columns,required:true,placeholder:false},
    {key:'description',label:'Coluna da descrição',type:'select',options:columns,required:true,placeholder:false},
    {key:'amount',label:'Coluna do valor',type:'select',options:columns,required:true,placeholder:false},
    {key:'type',label:'Coluna do tipo (opcional)',type:'select',options:columns,nullable:true,help:'Se o arquivo já indicar o tipo (débito/crédito, entrada/saída), selecione a coluna aqui. Tem prioridade sobre a detecção automática.'},
    {key:'date_format',label:'Formato da data',type:'select',options:DATE_FORMATS,required:true,default:'d/m/Y',placeholder:false},
    {key:'number_format',label:'Formato do número',type:'select',options:NUMBER_FORMATS,required:true,default:'pt-BR',placeholder:false},
    {key:'transaction_type',label:'Sem coluna de tipo, forçar todas como',type:'select',options:isCard?[['DESPESA','Sempre despesa']]:[['RECEITA','Sempre receita'],['DESPESA','Sempre despesa']],nullable:true,help:typeHelp},
    {key:'payment_method',label:'Forma de pagamento',type:'select',options:METHODS,nullable:true},
    {key:'expense_category_id',label:'Categoria padrão para despesas',type:'select',lookup:'expense_categories',nullable:true,numeric:true},
  ];
  if(!isCard)fields.push({key:'income_category_id',label:'Categoria padrão para receitas',type:'select',lookup:'income_categories',nullable:true,numeric:true});
  return fields;
}

function mappingPanel(imp){
  const fields=mappingFields(imp);const isCard=Boolean(imp.card_id);
  return `<section class="panel"><div class="import-toolbar"><div><h2>Mapear colunas</h2><p class="muted">Confira como cada coluna do arquivo será interpretada. Reenviar o mapeamento recalcula a prévia.</p></div></div>${isCard?'<div class="alert info" style="margin:0 22px">Importação vinculada a um cartão: apenas compras (despesas) são importadas como lançamento. Pagamentos e créditos do cartão aparecem desmarcados na revisão abaixo.</div>':''}<form id="import-mapping" class="import-mapping"><div class="form-grid">${fields.map(f=>fieldHtml(f,imp.column_mapping||{})).join('')}</div><div class="modal-actions" style="justify-content:flex-start"><button class="button primary" type="submit">Aplicar mapeamento</button></div></form></section>`;
}

function bindMapping(imp){
  const form=$('#import-mapping');
  form.onsubmit=async event=>{
    event.preventDefault();const button=$('button[type=submit]',form);button.disabled=true;
    try{
      const data=readForm(form,mappingFields(imp));
      await api(`/imports/${imp.id}/mapping`,'PUT',data);
      toast('Mapeamento aplicado.');
      await renderReview(imp.id);
    }catch(error){formErrors(form,error);button.disabled=false;}
  };
}

function rowTypeOptions(current,isCard){
  return (isCard?['DESPESA']:['DESPESA','RECEITA']).map(v=>`<option value="${v}" ${current===v?'selected':''}>${escape(label(v))}</option>`).join('');
}
function rowCategoryOptions(type,selected){
  const source=type==='RECEITA'?'income_categories':'expense_categories';
  const options=(lookups.categories??[]).filter(r=>r.status==='ATIVO'&&!r.parent_id&&(type==='RECEITA'?r.type==='RECEITA':r.type==='DESPESA'));
  return `<option value="">Selecione…</option>${options.map(o=>`<option value="${o.id}" ${Number(selected)===Number(o.id)?'selected':''}>${escape(o.name)}</option>`).join('')}`;
}

function rowsPanel(imp,result){
  const rows=result.items;
  if(!rows.length)return `<section class="panel" style="margin-top:24px">${empty('Nenhuma linha para revisar','O arquivo não contém linhas reconhecíveis.')}</section>`;
  const canReview=imp.status==='PREVIA'||imp.status==='CONCLUIDA';const isCard=Boolean(imp.card_id);
  return `<section class="panel" style="margin-top:24px"><div class="import-toolbar"><h2>Linhas do arquivo</h2>${canReview?'<div class="button-row"><button class="button secondary" id="save-all-rows">Salvar todas as linhas</button><button class="button primary" id="confirm-import">Confirmar importação</button></div>':''}</div>${canReview?'<p class="section-note" style="margin:0 22px 16px">Marque/desmarque a coluna de seleção com a caixa do cabeçalho, ajuste tipo e categoria como quiser e clique em "Salvar todas as linhas" para aplicar tudo de uma vez, sem precisar salvar linha por linha. Linhas já importadas não podem mais ser alteradas por aqui.</p>':''}<div class="table-scroll"><table><thead><tr>${canReview?'<th><input type="checkbox" id="select-all-rows" title="Selecionar/desmarcar todas as linhas desta página"></th>':''}<th>Linha</th><th>Data</th><th>Descrição</th><th>Tipo</th><th>Categoria</th><th>Valor</th><th>Situação</th>${canReview?'<th class="actions-heading">Ações</th>':''}</tr></thead><tbody>${rows.map(r=>{
    const m=r.mapped_data||{};const errors=r.validation_errors&&typeof r.validation_errors==='object'?Object.values(r.validation_errors).flat():[];
    const rowEditable=canReview&&r.status!=='IMPORTADA';
    if(!rowEditable)return `<tr>${canReview?'<td></td>':''}<td>${r.row_number}</td><td>${date(m.transaction_date)}</td><td class="primary-cell">${escape(m.description??'—')}</td><td>${escape(label(m.transaction_type))}</td><td>${escape((lookups.categories??[]).find(c=>Number(c.id)===Number(m.category_id))?.name??'—')}</td><td><span class="money-value ${m.transaction_type==='RECEITA'?'positive':'negative'}">${money(m.amount)}</span></td><td>${badge(r.status)}</td>${canReview?'<td></td>':''}</tr>`;
    return `<tr data-row="${r.id}"><td><input type="checkbox" class="row-selected" ${r.selected?'checked':''} ${r.status==='INVALIDA'?'disabled':''}></td><td>${r.row_number}</td><td>${date(m.transaction_date)}</td><td><input type="text" class="row-description" value="${escape(m.description??'')}" maxlength="255"></td><td><select class="row-type" ${isCard?'disabled title="Um cartão só registra despesas neste sistema."':''}>${rowTypeOptions(m.transaction_type,isCard)}</select></td><td><select class="row-category">${rowCategoryOptions(m.transaction_type,m.category_id)}</select></td><td><span class="money-value ${m.transaction_type==='RECEITA'?'positive':'negative'}">${money(m.amount)}</span></td><td>${badge(r.status)}${errors.length?`<div class="inline-warning">${errors.map(escape).join(' ')}</div>`:r.status==='DUPLICADA'?`<div class="inline-warning">${ROW_STATUS_HELP.DUPLICADA}</div>`:''}</td><td><button class="text-button" data-save="${r.id}">Salvar</button></td></tr>`;
  }).join('')}</tbody></table></div>${pagination(result.pagination)}</section>`;
}

function summaryPanel(imp){
  const counts=imp.counts;
  return `<section class="panel panel-pad"><div class="import-toolbar" style="padding:0"><div><h2>${escape(imp.original_filename)}</h2><p class="muted">${escape(imp.format)} enviado em ${date(imp.created_at)}</p></div>${badge(imp.status)}</div><div class="detail-summary" style="margin-top:20px"><div class="detail-stat"><span>Linhas no arquivo</span><strong>${counts.total}</strong></div><div class="detail-stat"><span>Selecionadas</span><strong>${counts.selected}</strong></div><div class="detail-stat"><span>Importadas</span><strong>${counts.imported}</strong></div><div class="detail-stat"><span>Duplicadas</span><strong>${counts.duplicates}</strong></div><div class="detail-stat"><span>Com erro</span><strong>${counts.invalid}</strong></div><div class="detail-stat"><span>Não selecionadas</span><strong>${counts.left_out||0}</strong></div></div>${counts.left_out?`<div class="alert info" style="margin-top:16px">${counts.left_out} linha(s) válida(s) ainda não foram selecionadas nem importadas. Revise e marque-as abaixo, depois confirme de novo — linhas já importadas não são duplicadas.</div>`:''}</section>`;
}
function renderSummary(imp){$('#summary-area').innerHTML=summaryPanel(imp);}

function rowUpdatePayload(tr){
  return {id:Number(tr.dataset.row),description:$('.row-description',tr).value.trim(),transaction_type:$('.row-type',tr).value,category_id:$('.row-category',tr).value?Number($('.row-category',tr).value):null,selected:$('.row-selected',tr).checked};
}

function bindRows(imp,page){
  const editable=imp.status==='PREVIA'||imp.status==='CONCLUIDA';
  const area=$('#rows-area');
  area.onclick=async event=>{
    const confirmBtn=event.target.closest('#confirm-import');
    if(confirmBtn){
      const leftOut=imp.counts.left_out||0;
      const warning=leftOut?`Atenção: ${leftOut} linha(s) válida(s) não estão marcadas e ficarão de fora. Você pode voltar aqui depois para selecioná-las e confirmar de novo, mas não fique sem revisar antes de continuar. `:'';
      confirmAction('Confirmar importação',`${warning}As linhas selecionadas serão gravadas como lançamentos${imp.counts.invalid?'. Linhas com erro permanecem de fora':''}.`,async()=>{await api(`/imports/${imp.id}/confirm`,'POST',{});toast('Importação concluída.');await renderReview(imp.id);});
      return;
    }
    const pageBtn=event.target.closest('[data-page]');
    if(pageBtn){await loadRows(imp,Number(pageBtn.dataset.page));return;}
    const saveBtn=event.target.closest('[data-save]');
    if(saveBtn&&editable){
      const {id,...data}=rowUpdatePayload(saveBtn.closest('tr'));
      try{
        await api(`/import-rows/${id}`,'PUT',data);
        Object.assign(imp,await api(`/imports/${imp.id}`));renderSummary(imp);
        toast('Linha atualizada.');await loadRows(imp,page);
      }catch(error){toast(error.message,true);}
      return;
    }
    const saveAllBtn=event.target.closest('#save-all-rows');
    if(saveAllBtn&&editable){
      const trs=[...area.querySelectorAll('tr[data-row]')];
      if(!trs.length){toast('Nenhuma linha para salvar nesta página.',true);return;}
      saveAllBtn.disabled=true;
      try{
        const updates=trs.map(rowUpdatePayload);
        const result=await api(`/imports/${imp.id}/rows`,'PUT',{updates});
        const failed=result.failed?.length??0;
        Object.assign(imp,result.import);renderSummary(imp);
        toast(failed?`${result.updated} linha(s) salvas, ${failed} com erro.`:`${result.updated} linha(s) salvas.`,Boolean(failed));
        await loadRows(imp,page);
      }catch(error){toast(error.message,true);saveAllBtn.disabled=false;}
      return;
    }
  };
  area.onchange=event=>{
    if(event.target.classList.contains('row-type')){const tr=event.target.closest('tr');$('.row-category',tr).innerHTML=rowCategoryOptions(event.target.value,null);return;}
    if(event.target.id==='select-all-rows'){const checked=event.target.checked;area.querySelectorAll('.row-selected:not(:disabled)').forEach(cb=>{cb.checked=checked;});}
  };
}

async function loadRows(imp,page=1){
  const result=await api(`/imports/${imp.id}/rows?per_page=100&page=${page}`);
  $('#rows-area').innerHTML=rowsPanel(imp,result);
  bindRows(imp,page);
}

async function renderReview(id){
  $('#page-actions').innerHTML='<a class="button secondary" href="?page=imports">← Todas as importações</a>';
  const imp=await api(`/imports/${id}`);
  $('#page-content').innerHTML=`<div id="summary-area"></div>${imp.status==='PREVIA'?mappingPanel(imp):''}<div id="rows-area" style="margin-top:0"></div>`;
  renderSummary(imp);
  if(imp.status==='PREVIA')bindMapping(imp);
  await loadRows(imp,1);
}
