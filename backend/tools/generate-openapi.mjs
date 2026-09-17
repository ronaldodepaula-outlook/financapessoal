import fs from 'node:fs';
import {planningContracts} from './openapi-planning.mjs';
import {reportsContracts} from './openapi-reports.mjs';
const file=new URL('../docs/openapi.json',import.meta.url);
const previous=JSON.parse(fs.readFileSync(file));
const spec={openapi:'3.0.3',info:{title:'Finanças Pessoais API'},servers:[{url:'http://127.0.0.1:8000'},{url:'http://localhost/financapessoal/backend/public'}],tags:[{name:'Infrastructure'}],paths:{'/api/health':previous.paths['/api/health']},components:{schemas:{HealthResponse:previous.components.schemas.HealthResponse,ErrorResponse:previous.components.schemas.ErrorResponse}}};
spec.info.version='0.2.0';spec.info.description='API financeira. Autenticação JWT e módulos implementados incrementalmente.';
spec.components.securitySchemes={bearerAuth:{type:'http',scheme:'bearer',bearerFormat:'JWT'}};
const string={type:'string'};const id={type:'integer',minimum:1};
const object=(properties,required=[])=>({type:'object',properties,required});
const error={'description':'Erro padronizado; errors contém campos em falhas de validação.','content':{'application/json':{schema:object({success:{type:'boolean'},message:string,data:{nullable:true},errors:{oneOf:[{type:'array',items:string},{type:'object',additionalProperties:{type:'array',items:string}}]}})}}};
function endpoint(path,method,tag,description,body,successSchema,options={}) {
  if(!spec.tags.some(t=>t.name===tag)) spec.tags.push({name:tag,description:tag});
  const parameters=path.includes('{id}')?[{name:'id',in:'path',required:true,schema:id}]:[];
  const operation={tags:[tag],operationId:method+path.replace(/\W+/g,'_'),summary:description,description,security:options.public?[]:[{bearerAuth:[]}],parameters,
  responses:{[options.status||200]:{description:'Operação concluída.',content:{'application/json':{schema:object({success:{type:'boolean',enum:[true]},message:string,data:successSchema||{nullable:true},errors:{type:'array',items:string}},['success','message','data','errors'])}}},401:error,404:error,409:error,422:error,429:error,500:error}};
  if(body) operation.requestBody={required:true,content:{'application/json':{schema:body,example:options.example||{}}}};
  spec.paths[path]??={};spec.paths[path][method]=operation;
}
const tokens=object({access_token:string,refresh_token:string,token_type:{type:'string',enum:['Bearer']},expires_in:id,refresh_expires_at:{type:'string',format:'date-time'}});
endpoint('/api/auth/login','post','Authentication','Autenticar com e-mail e senha; 5 tentativas/minuto por e-mail e 20 por IP.',object({email:{type:'string',format:'email'},password:{type:'string',format:'password'}},['email','password']),tokens,{public:true,example:{email:'usuario@example.com',password:'SenhaDefinidaNaInstalacao'}});
endpoint('/api/auth/refresh','post','Authentication','Rotacionar tokens; credenciais anteriores deixam de funcionar. Renovação máxima de 30 dias, configurável.',object({refresh_token:{type:'string',pattern:'^[a-f0-9]{96}$'}},['refresh_token']),tokens,{public:true,example:{refresh_token:'a'.repeat(96)}});
endpoint('/api/auth/me','get','Authentication','Consultar usuário autenticado sem senha ou segredos.',null,object({id,name:string,email:{type:'string',format:'email'},status:{type:'string',enum:['ATIVO','INATIVO']}}));
endpoint('/api/auth/logout','post','Authentication','Revogar a sessão atual, incluindo acesso e renovação.',null,{nullable:true});
const money={type:'string',pattern:'^\\d{1,13}(\\.\\d{1,2})?$',example:'100.00',description:'Valor decimal em BRL como string. Não enviar ponto flutuante.'};
const date={type:'string',format:'date'};
const optionalId={...id,nullable:true};
const status={type:'string',enum:['ATIVO','INATIVO']};
const paymentStatus={type:'string',enum:['PENDENTE','PAGA','CANCELADA']};
const methods={type:'string',enum:['PIX','DINHEIRO','DEBITO','CREDITO','TRANSFERENCIA','BOLETO','OUTROS']};
const page=schema=>object({items:{type:'array',items:schema},pagination:object({current_page:id,per_page:id,total:{type:'integer',minimum:0},last_page:id})});
function paginate(path,fields={}) {
  spec.paths[path].get.parameters.push(...Object.entries({page:id,per_page:{type:'integer',minimum:1,maximum:100,default:20},...fields}).map(([name,schema])=>({name,in:'query',required:false,schema})));
}
const catalogs={
  accounts:{tag:'Accounts',properties:{name:string,institution:{...string,nullable:true},account_type:{type:'string',enum:['CONTA_CORRENTE','POUPANCA','CARTEIRA','CONTA_DIGITAL','INVESTIMENTO']},initial_balance:{...money,pattern:'^-?\\d{1,13}(\\.\\d{1,2})?$'},status},required:['name','account_type'],example:{name:'Conta principal',account_type:'CONTA_CORRENTE',initial_balance:'1000.00'}},
  cards:{tag:'Cards',properties:{name:string,institution:{...string,nullable:true},brand:{...string,nullable:true},last_digits:{type:'string',pattern:'^\\d{4}$',nullable:true},credit_limit:money,closing_day:{type:'integer',minimum:1,maximum:31},due_day:{type:'integer',minimum:1,maximum:31},status},required:['name','credit_limit','closing_day','due_day'],example:{name:'Cartão principal',credit_limit:'5000.00',closing_day:20,due_day:27}},
  categories:{tag:'Categories',properties:{name:string,type:{type:'string',enum:['RECEITA','DESPESA']},parent_id:optionalId,status},required:['name','type'],example:{name:'Alimentação',type:'DESPESA'}},
  merchants:{tag:'Merchants',properties:{name:string,category_id:optionalId,subcategory_id:optionalId,status},required:['name'],example:{name:'Mercado Cometa'}},
};
for(const [resource,catalog] of Object.entries(catalogs)){
  const path='/api/'+resource,record=object({id,...catalog.properties});
  if(resource==='accounts')record.properties.current_balance={...money,pattern:'^-?\\d{1,13}(\\.\\d{1,2})?$',readOnly:true};
  if(resource==='cards')Object.assign(record.properties,{used_limit:{...money,readOnly:true},available_limit:{...money,pattern:'^-?'+money.pattern.slice(1),readOnly:true}});
  endpoint(path,'get',catalog.tag,'Listar cadastros do usuário com paginação.',null,page(record));
  paginate(path,{search:string,status,...(resource==='categories'?{type:catalog.properties.type,parent_id:optionalId}:{})});
  endpoint(path,'post',catalog.tag,'Criar cadastro do usuário autenticado.',object(catalog.properties,catalog.required),record,{status:201,example:catalog.example});
  endpoint(path+'/{id}','get',catalog.tag,'Consultar cadastro do usuário; registro de outro proprietário retorna 404.',null,record);
  endpoint(path+'/{id}','put',catalog.tag,'Atualizar os campos enviados. Categoria preserva tipo e hierarquia; saldo inicial fica bloqueado após movimentação.',object(catalog.properties),record,{example:{name:'Nome atualizado'}});
  endpoint(path+'/{id}','delete',catalog.tag,'Arquivar cadastro preservando histórico. Categoria com filhas ativas retorna 409.',null,{nullable:true});
}
const transaction=object({description:string,transaction_type:{type:'string',enum:['RECEITA','DESPESA']},account_id:optionalId,card_id:optionalId,category_id:id,subcategory_id:optionalId,merchant_id:optionalId,card_invoice_id:optionalId,amount:money,payment_method:methods,transaction_date:date,due_date:{...date,nullable:true},competence_year:{type:'integer',minimum:1900,maximum:2200},competence_month:{type:'integer',minimum:1,maximum:12},status:paymentStatus,is_fixed:{type:'boolean'},notes:{...string,nullable:true}},['description','transaction_type','category_id','amount','payment_method','transaction_date','competence_year','competence_month']);
const transactionExample={description:'Compra de supermercado',transaction_type:'DESPESA',account_id:1,category_id:1,amount:'100.10',payment_method:'PIX',transaction_date:'2027-01-10',competence_year:2027,competence_month:1,status:'PAGA'};
const transfer=object({source_account_id:id,destination_account_id:id,amount:money,transfer_date:date,description:{...string,nullable:true},status:paymentStatus,notes:{...string,nullable:true}},['source_account_id','destination_account_id','amount','transfer_date']);
for(const [resource,tag,schema,example] of [['transactions','Transactions',transaction,transactionExample],['transfers','Transfers',transfer,{source_account_id:1,destination_account_id:2,amount:'250.55',transfer_date:'2027-01-15',status:'PAGA'}]]){
  const path='/api/'+resource,record=object({id,...schema.properties});
  endpoint(path,'get',tag,'Listar movimentos do usuário com paginação e filtros.',null,page(record));paginate(path,{status:paymentStatus});
  endpoint(path,'post',tag,resource==='transactions'?'Criar receita/despesa. Crédito exige cartão sem conta; demais formas exigem conta. Categoria deve ser principal e do mesmo tipo.':'Transferir entre duas contas distintas do mesmo usuário; não gera receita ou despesa.',schema,record,{status:201,example});
  endpoint(path+'/{id}','get',tag,'Consultar movimento do usuário.',null,record);
  endpoint(path+'/{id}','put',tag,'Atualizar campos enviados e recalcular saldo. Parcelas geradas permitem somente status, notes e card_invoice_id.',{...schema,required:[]},record,{example:{status:'PAGA'}});
  endpoint(path+'/{id}','delete',tag,'Cancelar movimento sem apagar histórico e restaurar efeito no saldo.',null,{nullable:true});
}
paginate('/api/transactions',{search:string,date_from:date,date_to:date,category_id:id,account_id:id,card_id:id,merchant_id:id,transaction_type:transaction.properties.transaction_type,competence_year:transaction.properties.competence_year,competence_month:transaction.properties.competence_month});
const invoice=object({card_id:id,competence_year:transaction.properties.competence_year,competence_month:transaction.properties.competence_month,closing_date:date,due_date:date},['card_id','competence_year','competence_month','closing_date','due_date']);
const invoiceResponse=object({id,...invoice.properties,total_amount:money,paid_amount:money,outstanding_amount:money,status:paymentStatus});
endpoint('/api/card-invoices','get','Cards','Listar faturas, total de compras e valores pagos/em aberto.',null,page(invoiceResponse));paginate('/api/card-invoices',{card_id:id});
endpoint('/api/card-invoices','post','Cards','Criar fatura por cartão/competência com fechamento e vencimento informados explicitamente.',invoice,invoiceResponse,{status:201,example:{card_id:1,competence_year:2027,competence_month:1,closing_date:'2027-01-20',due_date:'2027-01-27'}});
endpoint('/api/card-invoices/{id}','get','Cards','Consultar fatura e saldos.',null,invoiceResponse);
endpoint('/api/card-invoices/{id}/link-transactions','post','Cards','Vincular à fatura os lançamentos a crédito do mesmo cartão e competência ainda sem fatura. Novos lançamentos e importações já vinculam automaticamente ao criar; use este endpoint para atualizar faturas existentes.',null,object({linked:{type:'integer'},...invoiceResponse.properties}),{example:{}});
const payment=object({account_id:id,amount:money,payment_date:date,notes:string},['account_id','amount','payment_date']);
endpoint('/api/card-invoices/{id}/payments','get','Cards','Listar pagamentos da fatura.',null,page(object({id,...payment.properties,status:paymentStatus})));paginate('/api/card-invoices/{id}/payments');
endpoint('/api/card-invoices/{id}/payments','post','Cards','Registrar pagamento parcial ou integral, sem nova despesa. Rejeita pagamento superior ao saldo. Compras ficam bloqueadas enquanto houver pagamentos ativos.',payment,object({id,...payment.properties,status:paymentStatus}),{status:201,example:{account_id:1,amount:'500.00',payment_date:'2027-01-27'}});
endpoint('/api/card-invoice-payments/{id}','delete','Cards','Cancelar pagamento, restaurando saldo da conta e dívida da fatura.',null,{nullable:true});
const installment=object({description:string,total_amount:money,total_installments:{type:'integer',minimum:1,maximum:600},start_date:{...date,description:'Primeiro vencimento; o dia é preservado e limitado ao último dia de meses curtos.'},account_id:optionalId,card_id:optionalId,category_id:id,subcategory_id:optionalId,payment_method:methods,notes:string},['description','total_amount','total_installments','start_date','category_id','payment_method']);
const purchase=object({id,...installment.properties,installment_amount:money,end_date:date,status:paymentStatus,paid_installments:{type:'integer'},remaining_installments:{type:'integer'},current_installment:{...id,nullable:true},next_due_date:{...date,nullable:true},outstanding_balance:money,schedule:{type:'array',items:object({id,...transaction.properties,installment_number:id})}});
endpoint('/api/installments','get','Installments','Listar compras parceladas e progresso calculado.',null,page(purchase));paginate('/api/installments',{status:paymentStatus});
endpoint('/api/installments','post','Installments','Criar compra e cronograma atomicamente. Centavos restantes vão às primeiras parcelas; total preservado. Nenhuma parcela é marcada paga automaticamente.',installment,purchase,{status:201,example:{description:'Notebook',total_amount:'1200.00',total_installments:12,start_date:'2027-01-31',account_id:1,category_id:1,payment_method:'BOLETO'}});
endpoint('/api/installments/{id}','get','Installments','Consultar compra e todas as parcelas. Saldo em aberto inclui parcelas pendentes vencidas.',null,purchase);
endpoint('/api/installments/{id}','delete','Installments','Cancelar somente parcelas pendentes, preservando as pagas. Conflito 409 se alguma estiver em fatura com pagamento ativo.',null,{nullable:true});
// Exemplos de resposta explícitos acompanham os contratos de todas as operações.
planningContracts({spec,endpoint,paginate,object,page,string,id,date,money,methods,paymentStatus});
reportsContracts({spec,endpoint,paginate,object,page,string,id,date,money,methods,paymentStatus});
for(const path of Object.values(spec.paths))for(const operation of Object.values(path)){
  if(!operation.responses)continue;
  const seen=new Set();operation.parameters=(operation.parameters||[]).filter(p=>{const key=p.in+':'+p.name;if(seen.has(key))return false;seen.add(key);return true;});
  for(const [code,response]of Object.entries(operation.responses)){
    const content=response.content?.['application/json'];if(!content||content.example)continue;
    content.example=Number(code)<400?{success:true,message:'Operação concluída.',data:{},errors:[]}:{success:false,message:'Não foi possível concluir a solicitação.',data:null,errors:[]};
  }
}
spec.info.version='0.8.0';
spec.components.responses={ApiError:error};
for(const path of Object.values(spec.paths))for(const operation of Object.values(path)){
  if(!operation.responses)continue;
  for(const code of Object.keys(operation.responses)){
    if(Number(code)>=400&&code!=='429'&&code!=='503')operation.responses[code]={$ref:'#/components/responses/ApiError'};
  }
}

function clean(node){
  if(!node||typeof node!=='object')return;
  if(Array.isArray(node.required)&&node.required.length===0)delete node.required;
  for(const child of Object.values(node))clean(child);
}
clean(spec);
fs.writeFileSync(file,JSON.stringify(spec,null,2)+'\n');
