import {config,$,api,escape,money,date,label,badge,lookups,prepareLookups,loadLookup,fieldHtml,readForm,formErrors,openModal,closeModal,confirmAction,toast,pagination,empty} from './core.js';
import {schemas,actionFields} from './schemas.js';

export function formModal(title,fields,record,onSubmit,options={}){
  const rendered=fields.map(f=>({...f}));const defaults=Object.fromEntries(rendered.filter(f=>f.default!==undefined).map(f=>[f.key,f.default]));const current={...defaults,...record};
  openModal(title,`${options.message?`<div class="alert info">${escape(options.message)}</div>`:''}<form id="record-form"><div class="form-grid">${rendered.map(f=>fieldHtml(f,current)).join('')}</div><div class="modal-actions"><button type="button" class="button secondary" data-close-modal>Cancelar</button><button type="submit" class="button primary">${escape(options.submit??'Salvar')}</button></div></form>`);
  const form=$('#record-form');
  const updateRelations=()=>{
    const value=readForm(form,rendered);value.transaction_type??=value.type;
    for(const key of ['category_id','subcategory_id','parent_id']){
      const field=rendered.find(f=>f.key===key),input=form.elements[key];if(!field?.lookup||!input)continue;
      const old=input.value;const holder=document.createElement('div');holder.innerHTML=fieldHtml(field,{...current,...value,[key]:old});input.innerHTML=holder.querySelector('select').innerHTML;
    }
    const method=form.elements.payment_method?.value;
    if(method){for(const[key,show]of [['card_id',method==='CREDITO'],['card_invoice_id',method==='CREDITO'],['account_id',method!=='CREDITO']]){const wrap=$(`[data-field="${key}"]`,form);if(wrap){wrap.hidden=!show;form.elements[key].disabled=!show;}}}
    const kind=form.elements.payment_type?.value;
    if(kind){const wrap=$('[data-field="loan_installment_id"]',form);if(wrap){wrap.hidden=kind!=='PARCELA';form.elements.loan_installment_id.disabled=kind!=='PARCELA';}}
    options.onChange?.(form,value);
  };
  form.addEventListener('change',updateRelations);updateRelations();
  form.onsubmit=async event=>{
    event.preventDefault();const button=$('button[type=submit]',form);button.disabled=true;
    try{let data=readForm(form,rendered);if(data.payment_method){if(data.payment_method==='CREDITO')data.account_id=null;else {data.card_id=null;data.card_invoice_id=null;}}
      if(data.payment_type&&data.payment_type!=='PARCELA')delete data.loan_installment_id;
      if(options.onlyChanged)data=Object.fromEntries(Object.entries(data).filter(([k,v])=>String(v??'')!==String(record[k]??'')));
      await onSubmit(data);closeModal();toast(options.success??'Salvo com sucesso.');
    }catch(error){formErrors(form,error);button.disabled=false;}
  };
}

export async function editRecord(resource,record={},done=()=>{}){
  await prepareLookups();if(resource==='transactions')await loadLookup('card-invoices');
  const schema=schemas[resource];let fields=schema.fields.filter(f=>!(record.id&&f.immutable));
  if(resource==='transactions'&&record.id&&(record.installment_id||record.fixed_expense_id||record.subscription_id||record.income_schedule_id))fields=fields.filter(f=>['status','notes','card_invoice_id'].includes(f.key));
  if(resource==='loans'&&record.id&&record.status!=='RASCUNHO')fields=fields.filter(f=>['name','institution','notes'].includes(f.key));
  if(resource==='budgets'&&record.id)fields=fields.filter(f=>['planned_amount','reason'].includes(f.key));
  if(resource==='transactions'&&!record.id&&config.definition.filters?.transaction_type)record={transaction_type:config.definition.filters.transaction_type,...record};
  const message=resource==='card-invoices'&&!record.id?'O total não é digitado aqui: ele é calculado automaticamente somando as compras a crédito lançadas nesta competência (e pode ser atualizado depois com "Vincular lançamentos").':undefined;
  formModal(`${record.id?'Editar':'Adicionar'} ${schema.title}`,fields,record,async data=>{if(!Object.keys(data).length)return;await api(`/${resource}${record.id?'/'+record.id:''}`,record.id?'PUT':'POST',data);delete lookups[resource];await done();},{onlyChanged:Boolean(record.id)&&resource!=='budgets',message});
}

function valueCell(row,column){
  const [key,,type]=column,value=row[key];
  if(type==='money')return `<span class="money-value${String(value).startsWith('-')?' negative':''}">${escape(money(value))}</span>`;
  if(type==='transaction-money')return `<span class="money-value ${row.transaction_type==='RECEITA'?'positive':'negative'}">${row.transaction_type==='RECEITA'?'+':'−'} ${escape(money(value))}</span>`;
  if(type==='date')return escape(date(value));if(type==='badge')return badge(value);if(type==='active')return badge(value?'ATIVO':'INATIVO');if(type==='label')return escape(label(value));if(type==='percent')return escape(value??'0')+'%';
  if(['category','account','card'].includes(type)){const resource={category:'categories',account:'accounts',card:'cards'}[type];return escape(row[key.replace('_id','')]?.name??lookups[resource]?.find(r=>Number(r.id)===Number(value))?.name??'—');}
  if(type==='source')return escape(row.account?.name??row.card?.name??(row.cash_flow_effect===false?'Desconto em folha':'—'));
  return escape(value??'—');
}
export function recordTable(resource,rows,options={}){
  const schema=schemas[resource];
  if(!rows.length)return empty('Tudo pronto para começar',`Seus registros de ${schema.title} aparecerão aqui.`,options.noCreate?'':'<button class="button primary" data-new>Adicionar registro</button>');
  return `<div class="table-scroll"><table><thead><tr>${schema.columns.map(([,name])=>`<th>${escape(name)}</th>`).join('')}<th class="actions-heading">Ações</th></tr></thead><tbody>${rows.map(row=>`<tr>${schema.columns.map((column,i)=>`<td ${i===0?'class="primary-cell"':''}>${valueCell(row,column)}</td>`).join('')}<td><div class="row-actions">${schema.detail?`<button class="text-button" data-detail="${row.id}">Detalhes</button>`:''}${!schema.noEdit&&!row.loan_id?`<button class="text-button" data-edit="${row.id}">Editar</button>`:''}${row.loan_id?`<a class="text-button" href="?page=loans&record=${row.loan_id}">Contrato</a>`:''}${!schema.noDelete&&!row.loan_id?`<button class="text-button danger-text" data-delete="${row.id}" aria-label="${['transactions','transfers','installments','loans'].includes(resource)?'Cancelar':'Arquivar'} ${escape(row.name??row.description??'registro')}">${['transactions','transfers','installments','loans'].includes(resource)?'Cancelar':'Arquivar'}</button>`:''}</div></td></tr>`).join('')}</tbody></table></div>`;
}

export async function mountCrud(resource){
  await prepareLookups();const schema=schemas[resource];let rows=[],page=1;const filters={...config.definition.filters};
  $('#page-actions').innerHTML=`${['fixed-expenses','subscriptions','income-schedules'].includes(resource)?'<button class="button secondary" id="generate-forecasts">Gerar previsões</button>':''}<button class="button primary" data-new>＋ Adicionar ${escape(schema.title)}</button>`;
  const filterFields=[];
  if(['transactions','accounts','cards','categories','merchants','fixed-expenses','subscriptions','income-schedules'].includes(resource))filterFields.push({key:'search',label:'Buscar',type:'text',wide:true});
  if(resource==='transactions')filterFields.push({key:'date_from',label:'De',type:'date'},{key:'date_to',label:'Até',type:'date'},{key:'category_id',label:'Categoria',lookup:'categories'},{key:'account_id',label:'Conta',lookup:'accounts'},{key:'card_id',label:'Cartão',lookup:'cards'},{key:'status',label:'Situação',type:'select',options:['PAGA','PENDENTE','CANCELADA']});
  else if(['installments','loans','transfers','goals'].includes(resource))filterFields.push({key:'status',label:'Situação',type:'select',options:resource==='loans'?['RASCUNHO','ATIVO','QUITADO','CANCELADO']:resource==='goals'?['ATIVA','CONCLUIDA']:['PAGA','PENDENTE','CANCELADA']});
  $('#page-content').innerHTML=`<section class="panel">${filterFields.length?`<form id="list-filters" class="filters">${filterFields.map(f=>fieldHtml(f,filters)).join('')}<div class="filter-buttons"><button class="button secondary" type="submit">Filtrar</button><button type="reset" class="text-button">Limpar</button></div></form>`:''}<div id="records-area"></div></section>`;
  async function load(){const result=await api(`/${resource}?${new URLSearchParams({...filters,page,per_page:15})}`);rows=result.items;$('#records-area').innerHTML=recordTable(resource,rows)+pagination(result.pagination);}
  const safe=action=>async event=>{try{await action(event);}catch(error){toast(error.message,true);}};
  $('#page-actions').onclick=safe(async event=>{if(event.target.closest('[data-new]'))await editRecord(resource,{},load);if(event.target.closest('#generate-forecasts'))formModal('Gerar previsões',actionFields.forecast,{},async data=>{const result=await api('/forecasts/generate','POST',data);await load();toast(`${result.created} previsão(ões) criada(s).`);});});
  $('#page-content').onclick=safe(async event=>{
    const target=event.target.closest('button');if(!target)return;
    if(target.hasAttribute('data-page')){page=Number(target.dataset.page);await load();}
    if(target.hasAttribute('data-new'))await editRecord(resource,{},load);
    if(target.dataset.edit){const row=await api(`/${resource}/${target.dataset.edit}`);await editRecord(resource,row,load);}
    if(target.dataset.detail)await showDetail(resource,Number(target.dataset.detail),load);
    if(target.dataset.delete){const row=rows.find(r=>r.id===Number(target.dataset.delete));confirmAction('Confirmar alteração',`O registro “${row.name??row.description??'#'+row.id}” será ${['transactions','transfers','installments','loans'].includes(resource)?'cancelado':'arquivado'}. O histórico será preservado.`,async()=>{await api(`/${resource}/${row.id}`,'DELETE');delete lookups[resource];await load();toast('Registro atualizado.');});}
  });
  if($('#list-filters')){$('#list-filters').onsubmit=safe(async e=>{e.preventDefault();Object.keys(filters).forEach(k=>delete filters[k]);Object.assign(filters,config.definition.filters,readForm(e.target,filterFields));for(const key of Object.keys(filters))if(filters[key]===null||filters[key]==='')delete filters[key];page=1;await load();});$('#list-filters').onreset=()=>setTimeout(()=>$('#list-filters').requestSubmit(),0);}
  await load();const selected=new URLSearchParams(location.search).get('record');if(selected&&/^\d+$/.test(selected)&&schema.detail)await showDetail(resource,Number(selected),load);
}

export async function showDetail(resource,id,done=()=>{},historyPage=1){
  const row=await api(`/${resource}/${id}`);await prepareLookups();let html='';let history=null;let balances=null;
  const stat=(title,value)=>`<div class="detail-stat"><span>${escape(title)}</span><strong>${escape(value)}</strong></div>`;
  if(resource==='loans'){
    [history,balances]=await Promise.all([api(`/loans/${id}/payments?per_page=20&page=${historyPage}`),api(`/loans/${id}/balances?per_page=100`)]);
    html=`<div class="detail-summary">${stat('Valor contratado',money(row.principal_amount))}${stat('Parcela',money(row.installment_amount))}${stat('Pagas / total',`${row.paid_installments} / ${row.installments}`)}${stat('Saldo informado',money(row.outstanding_balance))}${stat('Taxa mensal',row.interest_rate?row.interest_rate+'%':'Não informada')}${stat('CET mensal / anual',`${row.cet??'—'}% / ${row.annual_cet??'—'}%`)}</div><div class="detail-status">${badge(row.status)}<span>Saldo atualizado em ${date(row.balance_reported_at)}</span></div>`;
    if(row.status==='RASCUNHO')html+='<div class="alert info">Confirme o primeiro vencimento e como a parcela é paga para gerar o cronograma.</div><button class="button primary" data-action="loan-activate">Confirmar e ativar contrato</button>';
    if(row.status==='ATIVO')html+='<div class="button-row"><button class="button primary" data-action="loan-payment">Registrar pagamento</button><button class="button secondary" data-action="loan-balance">Informar saldo devedor</button></div>';
    html+=`<h3 class="section-title">Histórico do saldo informado</h3>${balances.items.length?'<div class="chart-wrap small-chart"><canvas id="loan-history-chart" aria-label="Evolução dos saldos informados" role="img"></canvas></div>':'<p class="muted">Nenhum saldo informado. O total de parcelas não equivale ao saldo devedor.</p>'}`;
  }
  if(resource==='installments')html=`<div class="detail-summary">${stat('Total da compra',money(row.total_amount))}${stat('Parcelas pagas',`${row.paid_installments} / ${row.total_installments}`)}${stat('Saldo pendente',money(row.outstanding_balance))}</div>`;
  if(['loans','installments'].includes(resource))html+=`<h3 class="section-title">Cronograma de parcelas</h3>${row.schedule?.length?`<div class="table-scroll schedule-scroll"><table><thead><tr><th>Parcela</th><th>Vencimento</th><th>Valor</th><th>Situação</th>${resource==='installments'?'<th>Ação</th>':''}</tr></thead><tbody>${row.schedule.map(r=>`<tr><td>${r.number??r.installment_number}</td><td>${date(r.due_date??r.transaction_date)}</td><td>${money(r.amount)}</td><td>${badge(r.status)}</td>${resource==='installments'?`<td>${r.status==='PENDENTE'?`<button class="text-button" data-settle="${r.id}">Marcar como paga</button>`:''}</td>`:''}</tr>`).join('')}</tbody></table></div>`:'<p class="muted">O cronograma será criado após ativar o contrato.</p>'}`;
  if(resource==='card-invoices'){
    history=await api(`/card-invoices/${id}/payments?per_page=20&page=${historyPage}`);html=`<div class="detail-summary">${stat('Compras',money(row.total_amount))}${stat('Pago',money(row.paid_amount))}${stat('Em aberto',money(row.outstanding_amount))}</div><div class="alert info">O pagamento da fatura movimenta a conta sem criar outra despesa. Novos lançamentos a crédito nesta competência já são vinculados automaticamente; use "Vincular lançamentos" para atualizar o total após importar um extrato ou editar compras antigas.</div><div class="button-row"><button class="button secondary" data-link-invoice="${id}">Vincular lançamentos do mês</button><button class="button primary" data-action="invoice-payment">Registrar pagamento</button></div>`;
  }
  if(resource==='goals'){
    history=await api(`/goals/${id}/contributions?per_page=20&page=${historyPage}`);html=`<div class="detail-summary">${stat('Objetivo',money(row.target_amount))}${stat('Reservado',money(row.saved_amount))}${stat('Falta reservar',money(row.remaining_amount))}</div><div class="progress-track"><i style="width:${Math.min(100,Number(row.progress_percent))}%"></i></div><p class="muted">${escape(row.progress_percent)}% da meta · Prazo: ${date(row.target_date)}</p>${row.status==='ATIVA'?'<button class="button primary" data-action="contribution">Adicionar contribuição</button>':''}`;
  }
  if(history)html+=`<h3 class="section-title">${resource==='goals'?'Contribuições':'Pagamentos'} registrados</h3>${history.items.length?`<div class="table-scroll"><table><thead><tr><th>Data</th><th>Tipo</th><th>Valor</th><th>Situação</th><th>Ação</th></tr></thead><tbody>${history.items.map(p=>`<tr><td>${date(p.payment_date??p.contribution_date)}</td><td>${escape(label(p.payment_type??(resource==='goals'?'Reserva':'Pagamento')))}</td><td>${money(p.amount)}</td><td>${badge(p.cancelled_at?'CANCELADA':p.status??'PAGA')}</td><td>${!p.cancelled_at&&p.status!=='CANCELADA'?`<button class="text-button danger-text" data-reverse="${p.id}">Estornar</button>`:''}</td></tr>`).join('')}</tbody></table></div>${pagination(history.pagination)}`:'<p class="muted">Nenhum registro ainda.</p>'}`;
  openModal(row.name??row.description??'Detalhes da fatura',html,'Acompanhe cada etapa');
  if(balances?.items.length&&window.Chart){const points=[...balances.items].sort((a,b)=>a.reported_at.localeCompare(b.reported_at));new Chart($('#loan-history-chart'),{type:'line',data:{labels:points.map(p=>date(p.reported_at)),datasets:[{label:'Saldo informado',data:points.map(p=>Number(p.outstanding_balance)),borderColor:'#286b5d',backgroundColor:'#dceee5',fill:true,tension:.3}]},options:{responsive:true,maintainAspectRatio:false}});}
  $('#modal-body').onclick=async event=>{
    const target=event.target.closest('button');if(!target)return;
    try{
      if(target.dataset.page){await showDetail(resource,id,done,Number(target.dataset.page));return;}
      if(target.dataset.linkInvoice){await api(`/card-invoices/${target.dataset.linkInvoice}/link-transactions`,'POST',{}).then(result=>toast(result.linked?`${result.linked} lançamento(s) vinculado(s).`:'Nenhum lançamento novo para vincular.'));await showDetail(resource,id,done,historyPage);return;}
      if(target.dataset.settle){confirmAction('Registrar parcela paga','Confirme que este pagamento já foi realizado.',async()=>{await api('/transactions/'+target.dataset.settle,'PUT',{status:'PAGA'});await done();});return;}
      if(target.dataset.reverse){const endpoint=resource==='loans'?'loan-payments':resource==='goals'?'goal-contributions':'card-invoice-payments';confirmAction('Estornar registro','O efeito deste registro será desfeito, preservando o histórico.',async()=>{await api(`/${endpoint}/${target.dataset.reverse}`,'DELETE');await done();});return;}
      const action=target.dataset.action;if(!action)return;let fields=actionFields[action].map(f=>({...f}));let preset={};let message='';
      if(action==='loan-activate'){preset=row;message='Desconto em folha exige renda líquida confirmada em Configurações. Datas do contrato precisam corresponder às parcelas.';}
      if(action==='loan-payment'){
        fields.find(f=>f.key==='loan_installment_id').options=row.schedule.filter(s=>s.status==='PENDENTE').map(s=>[s.id,`Parcela ${s.number} · ${date(s.due_date)} · ${money(s.amount)}`]);preset={account_id:row.account_id};message='Parcela exige valor integral. Amortização registra o valor efetivo; o saldo devedor não é estimado.';
      }
      if(action==='invoice-payment')preset={amount:row.outstanding_amount};
      const endpoint={'loan-activate':`/loans/${id}/activate`,'loan-payment':`/loans/${id}/payments`,'loan-balance':`/loans/${id}/balances`,'invoice-payment':`/card-invoices/${id}/payments`,contribution:`/goals/${id}/contributions`}[action];
      formModal(target.textContent,fields,preset,async data=>{await api(endpoint,'POST',data);await done();},{message,onChange:(form,data)=>{if(action==='loan-payment'&&data.payment_type==='PARCELA'&&data.loan_installment_id){const selected=row.schedule.find(s=>s.id===Number(data.loan_installment_id));if(selected)form.elements.amount.value=selected.amount;}}});
    }catch(error){toast(error.message,true);}
  };
}
