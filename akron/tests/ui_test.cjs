/* Navegador Chromium com Playwright instalado fora do projeto. Banco de TESTES. */
const {chromium}=require('playwright');const fs=require('fs');const assert=require('node:assert/strict');
(async()=>{
 const base=process.env.AKRON_TEST_URL||'http://127.0.0.1:8000';
 const d=JSON.parse(fs.readFileSync(process.env.AKRON_TEST_CONTEXT||'/tmp/context.json','utf8'));
 const browser=await chromium.connectOverCDP(process.env.AKRON_CDP||'http://127.0.0.1:9222');
 const context=await browser.newContext({viewport:{width:1440,height:1000}});const page=await context.newPage();let checks=0;const erros=[];
 page.on('pageerror',e=>erros.push(e.message));page.on('dialog',dialog=>dialog.accept());
 const check=(v,t)=>{assert(v,t);checks++;console.log('PASS',t);};
 await page.goto(base);await page.locator('[data-dialog]').click();check(await page.locator('dialog').isVisible(),'explicação do sistema abre no botão de ajuda');await page.locator('dialog button').click();
 await page.locator('#login').fill('admin');await page.locator('#senha').fill('Admin@123');await Promise.all([page.waitForURL('**/painel.php'),page.locator('#btnLogin').click()]);
 check(await page.locator('.modulo-card[href*="perfil.php"]').count()>0,'Configurações pessoais no painel');
 const paginas=[['painel',`dashboard/painel.php?estoque_id=${d.estoque}`],['estoques','estoques/listar.php'],['estoque',`estoques/detalhes.php?id=${d.estoque}`],['sensores','sensores/listar_sensores.php'],['sensor',`sensores/detalhes.php?id=${d.sensor}`],['monitoramento',`graficos/graficos_sensores.php?estoque_id=${d.estoque}`],['alertas',`graficos/alerta.php?estoque_id=${d.estoque}`],['relatorios',`relatorios/relatorio_qualidade.php?estoque_id=${d.estoque}`],['usuarios','usuarios/gestao_usuarios.php'],['conta','usuarios/perfil.php'],['cadastro','usuarios/cadastro.php'],['editar-usuario',`usuarios/editar.php?id=${d.usuario}`],['editar-sensor',`sensores/editar_sensores.php?id=${d.sensor}`],['editar-estoque',`estoques/formulario.php?id=${d.estoque}`]];
 fs.mkdirSync('/app/docs/capturas-v3',{recursive:true});
 for(const [width,height,nome] of [[1440,1000,'desktop'],[820,1180,'tablet'],[390,844,'celular']]){
  await page.setViewportSize({width,height});
  for(const [tema,esquema] of [['claro','light'],['escuro','dark']]){
   await page.emulateMedia({colorScheme:esquema});
   for(const [rotulo,rota] of paginas){
    await page.goto(base+'/app/views/'+rota);await page.waitForLoadState('networkidle');
    const overflow=await page.evaluate(()=>({doc:document.documentElement.scrollWidth,view:innerWidth,els:[...document.querySelectorAll('main *')].filter(e=>e.getBoundingClientRect().right>innerWidth+2).slice(0,5).map(e=>e.tagName+'.'+e.className)}));
    check(overflow.doc<=width+2,`${nome} ${tema} ${rotulo} sem conteúdo cortado ${JSON.stringify(overflow)}`);
    check(!erros.length,`JavaScript ${rotulo}: ${erros.join(';')}`);
    if(['estoques','monitoramento','sensor','alertas','conta'].includes(rotulo)&&((nome==='desktop'&&tema==='claro')||(nome==='celular'&&tema==='escuro')))await page.screenshot({path:`/app/docs/capturas-v3/${rotulo}-${nome}-${tema}.png`,fullPage:true});
   }
  }
 }
 await page.setViewportSize({width:1440,height:1000});
 await page.goto(`${base}/app/views/estoques/detalhes.php?id=${d.estoque}`);
 check(await page.locator('#historico-alertas .alerta-card').count()===5,'estoque mostra cinco alertas inicialmente');
 await page.locator('[data-destino="historico-alertas"]').click();await page.waitForFunction(()=>document.querySelectorAll('#historico-alertas .alerta-card').length===15);
 check(true,'Mostrar mais acrescenta dez alertas');
 await page.goto(`${base}/app/views/graficos/graficos_sensores.php?estoque_id=${d.estoque}`);
 check(await page.locator('#monitor-atuais .sensor-atual').count()===6,'seis tipos nos valores atuais');
 const graficos=await page.evaluate(()=>Object.values(Chart.instances).map(g=>({labels:g.data.labels,ticks:g.scales.x.ticks.map(t=>t.label),tooltip:g.options.plugins.tooltip.callbacks.title([{label:g.data.labels[0]}])})));
 check(graficos.length===6&&graficos.every(g=>g.ticks.every(t=>!String(t).includes(':'))&&g.tooltip.includes(':')),'eixo só com datas e tooltip com horário preservado');
 await page.goto(`${base}/app/views/usuarios/cadastro.php`);const opcao=page.locator(`input[name="estoque_ids[]"][value="${d.estoque}"]`);await opcao.check();check(await opcao.isChecked(),'estoque selecionável por cartão');
 await page.goto(`${base}/app/views/usuarios/perfil.php`);await page.locator('.tema-escuro').click();await Promise.all([page.waitForURL('**/perfil.php'),page.getByRole('button',{name:'Salvar alterações'}).click()]);await page.waitForLoadState('networkidle');
 await page.reload();check(await page.locator('html').getAttribute('data-tema')==='escuro','tema da conta permanece depois de recarregar');
 await page.locator('.tema-automatico').click();await page.getByRole('button',{name:'Salvar alterações'}).click();await page.waitForLoadState('networkidle');await page.emulateMedia({colorScheme:'light'});await page.waitForTimeout(200);check(await page.locator('html').getAttribute('data-tema')==='claro','tema automático acompanha o dispositivo');
 await page.goto(`${base}/app/views/estoques/formulario.php?id=${d.estoque}`);const desativar=page.getByRole('button',{name:'Desativar estoque',exact:true});await desativar.click();check(await page.getByRole('button',{name:'Confirmar desativação'}).isVisible(),'primeiro clique apenas arma confirmação vermelha');await page.getByRole('button',{name:'Cancelar operação'}).click();check(await desativar.isVisible(),'cancelamento restaura botão');
 await desativar.click();await page.getByRole('button',{name:'Confirmar desativação'}).click();await page.waitForLoadState('networkidle');check((await page.locator('[name="status"]').inputValue())==='inativo','segundo clique confirma desativação');
 await page.locator('[name="status"]').selectOption('ativo');await page.getByRole('button',{name:'Salvar estoque',exact:true}).click();await page.waitForLoadState('networkidle');check((await page.locator('[name="status"]').inputValue())==='ativo','reativação do estoque preserva o cadastro');
 check(!erros.length,'nenhum erro JavaScript nas interações');console.log(checks+' verificações de navegador passaram.');await context.close();await browser.close();
})().catch(e=>{console.error(e);process.exit(1);});
