import {config,$,errorPanel,initShell,empty} from './core.js';
import {mountCrud} from './crud.js';
import {mountDashboard,mountBudgets,mountFortnight,mountReports,mountSettings} from './pages.js';
import {mountImports} from './imports.js';
import {mountCategories} from './categories.js';
initShell();
try{
  if(config.page==='categories')await mountCategories();
  else if(config.definition.resource)await mountCrud(config.definition.resource);
  else if(config.page==='dashboard')await mountDashboard();
  else if(config.page==='budgets')await mountBudgets();
  else if(config.page==='fortnight')await mountFortnight();
  else if(config.page==='reports')await mountReports();
  else if(config.page==='settings')await mountSettings();
  else if(config.page==='imports')await mountImports();
  else $('#page-content').innerHTML=empty('Página não encontrada','Escolha uma opção no menu para continuar.','<a class="button primary" href="?page=dashboard">Ir para visão geral</a>');
}catch(error){$('#page-content').innerHTML=errorPanel(error);}
