import {$,money,months} from './core.js';
const colors=['#3e7561','#a7be85','#e0bd75','#7c9f98','#cab69f','#698455','#d1d8c2'];
let charts=[];
export function destroyCharts(){for(const chart of charts)chart.destroy();charts=[];}
export function chart(id,type,labels,datasets,options={}){
  const canvas=$(id);if(!canvas||!window.Chart)return;
  Chart.defaults.font.family='Manrope, sans-serif';Chart.defaults.font.size=10;Chart.defaults.color='#8a978a';Chart.defaults.plugins.legend.display=false;
  charts.push(new Chart(canvas,{type,data:{labels,datasets},options:{responsive:true,maintainAspectRatio:false,interaction:{intersect:false,mode:'index'},plugins:{tooltip:{backgroundColor:'#234b3a',padding:12,callbacks:{label:context=>`${context.dataset.label??context.label}: ${money(String(context.parsed.y??context.parsed))}`}},legend:{display:false}},scales:type==='doughnut'?{}:{x:{grid:{display:false},border:{display:false},ticks:{maxRotation:0}},y:{border:{display:false},grid:{color:'#edf2e9'},beginAtZero:true,ticks:{callback:value=>Number(value).toLocaleString('pt-BR')}}},...options}}));
}
export function dashboardCharts(data,onCategoryClick){
  destroyCharts();
  chart('#monthly-chart','line',months.map(m=>m.slice(0,3)),[{label:'Receitas recebidas',data:data.monthly_evolution.map(r=>Number(r.income)),borderColor:'#377560',backgroundColor:'#b4c79b30',fill:true,tension:.35,pointRadius:2,borderWidth:2},{label:'Despesas pagas',data:data.monthly_evolution.map(r=>Number(r.expenses)),borderColor:'#d4b36d',backgroundColor:'transparent',tension:.35,pointRadius:2,borderWidth:2}]);
  const categories=data.by_category.filter(r=>Number(r.expenses)>0);
  chart('#category-chart','doughnut',categories.map(r=>r.label),[{data:categories.map(r=>Number(r.expenses)),backgroundColor:colors,borderWidth:4,borderColor:'#fff',hoverOffset:5}],{cutout:'75%',onClick:(evt,elements)=>{if(elements.length&&onCategoryClick)onCategoryClick(categories[elements[0].index].key);}});
  chart('#fortnight-chart','bar',data.fortnight.periods.map(p=>p.period==='01_15'?'01 a 15':'16 ao fim'),[{label:'Renda esperada',data:data.fortnight.periods.map(p=>Number(p.expected_income)),backgroundColor:'#8bab7a',borderRadius:5},{label:'Despesas comprometidas',data:data.fortnight.periods.map(p=>Number(p.committed_expenses)),backgroundColor:'#ddc48f',borderRadius:5}]);
  const budget=data.budget.items.slice(0,7);
  chart('#budget-chart','bar',budget.map(r=>r.subcategory?.name??r.category?.name??'Categoria'),[{label:'Planejado',data:budget.map(r=>Number(r.planned_amount)),backgroundColor:'#e5ecdd',borderRadius:3},{label:'Realizado',data:budget.map(r=>Number(r.actual_amount)),backgroundColor:'#648664',borderRadius:3}],{indexAxis:'y'});
  chart('#future-chart','bar',data.future_installments.slice(0,12).map(r=>r.label),[{label:'Parcelas pendentes',data:data.future_installments.slice(0,12).map(r=>Number(r.pending_expenses)),backgroundColor:'#9fb787',borderRadius:4}]);
}
export {colors};
