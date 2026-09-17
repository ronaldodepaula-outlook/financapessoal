import {$,api,escape,badge,toast,confirmAction,empty} from './core.js';
import {formModal} from './crud.js';

let categories=[];

async function loadAll(){
  let rows=[],page=1;
  for(;;){
    const result=await api(`/categories?per_page=100&page=${page}`);
    rows.push(...result.items);
    if(page>=result.pagination.last_page)break;
    page++;
  }
  categories=rows;
}

function sortByName(list){return [...list].sort((a,b)=>a.name.localeCompare(b.name,'pt-BR'));}

function categoryRow(cat,isChild){
  return `<div class="category-row${isChild?' sub':''}" data-id="${cat.id}">
    <span class="category-name">${escape(cat.name)}</span>${badge(cat.status)}
    <div class="row-actions">
      ${!isChild&&cat.status==='ATIVO'?`<button class="text-button" data-add-sub="${cat.id}">＋ Subcategoria</button>`:''}
      <button class="text-button" data-edit-cat="${cat.id}">Editar</button>
      <button class="text-button danger-text" data-archive-cat="${cat.id}">Arquivar</button>
    </div>
  </div>`;
}

function typeSection(type,title){
  const parents=sortByName(categories.filter(c=>c.type===type&&!c.parent_id));
  const body=parents.length?parents.map(p=>{
    const children=sortByName(categories.filter(c=>c.parent_id===p.id));
    return `<div class="category-node">${categoryRow(p,false)}${children.length?`<div class="category-children">${children.map(c=>categoryRow(c,true)).join('')}</div>`:''}</div>`;
  }).join(''):empty('Nenhuma categoria ainda',`Crie a primeira categoria de ${title.toLowerCase()}.`);
  return `<section class="panel panel-pad"><div class="panel-header"><div><h2>${escape(title)}</h2><p>Categorias principais e suas subcategorias</p></div><button class="button secondary" data-add-parent="${type}">＋ Nova categoria</button></div>${body}</section>`;
}

function render(){
  $('#page-content').innerHTML=`<div class="dashboard-grid">${typeSection('DESPESA','Despesas')}${typeSection('RECEITA','Receitas')}</div><p class="section-note">Tipo e categoria principal não podem ser alterados depois de criados, para preservar o histórico dos lançamentos já categorizados. Para reorganizar, crie uma nova categoria e arquive a antiga (só é possível arquivar quando não houver subcategorias ativas nela).</p>`;
}

const NAME_FIELD=[{key:'name',label:'Nome',type:'text',required:true,wide:true}];

async function reload(){await loadAll();render();bind();}

function bind(){
  $('#page-content').onclick=async event=>{
    const addParent=event.target.closest('[data-add-parent]');
    if(addParent){
      const type=addParent.dataset.addParent;
      formModal(`Nova categoria de ${type==='DESPESA'?'despesa':'receita'}`,NAME_FIELD,{},async data=>{
        await api('/categories','POST',{...data,type});
        await reload();
      });
      return;
    }
    const addSub=event.target.closest('[data-add-sub]');
    if(addSub){
      const parent=categories.find(c=>c.id===Number(addSub.dataset.addSub));
      formModal(`Nova subcategoria em ${parent.name}`,NAME_FIELD,{},async data=>{
        await api('/categories','POST',{...data,type:parent.type,parent_id:parent.id});
        await reload();
      },{message:`A subcategoria herda o tipo (${parent.type==='DESPESA'?'despesa':'receita'}) da categoria principal.`});
      return;
    }
    const editBtn=event.target.closest('[data-edit-cat]');
    if(editBtn){
      const cat=categories.find(c=>c.id===Number(editBtn.dataset.editCat));
      formModal(`Editar ${cat.parent_id?'subcategoria':'categoria'}`,[{key:'name',label:'Nome',type:'text',required:true,wide:true},{key:'status',label:'Situação',type:'select',options:['ATIVO','INATIVO'],required:true}],cat,async data=>{
        if(!Object.keys(data).length)return;
        await api(`/categories/${cat.id}`,'PUT',data);
        await reload();
      },{onlyChanged:true});
      return;
    }
    const archiveBtn=event.target.closest('[data-archive-cat]');
    if(archiveBtn){
      const cat=categories.find(c=>c.id===Number(archiveBtn.dataset.archiveCat));
      confirmAction('Arquivar categoria',`"${cat.name}" deixará de aparecer para novos lançamentos. O histórico já registrado é preservado.`,async()=>{
        try{await api(`/categories/${cat.id}`,'DELETE');}
        catch(error){toast(error.status===409?'Arquive as subcategorias ativas desta categoria antes.':error.message,true);return;}
        toast('Categoria arquivada.');
        try{await reload();}
        catch(error){console.error('Falha ao atualizar a lista após arquivar categoria.',error);}
      });
      return;
    }
  };
}

export async function mountCategories(){
  $('#page-actions').innerHTML='';
  await loadAll();
  render();
  bind();
}
