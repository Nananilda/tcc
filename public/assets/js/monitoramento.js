/* Apresentação comum do monitoramento. Dados sempre tratados como texto. */
const rotulosAmbientais = { normal:'Normal', atencao:'Atenção', critico:'Crítico', info:'Informativo', online:'Online', sem_comunicacao:'Sem comunicação', inativo:'Inativo', pendente:'Pendente', reconhecido:'Reconhecido', normalizado:'Normalizado', resolvido:'Resolvido' };
function elemento(tag, texto, classe) { const el=document.createElement(tag); if(texto!==undefined)el.textContent=texto; if(classe)el.className=classe;return el; }
function mostrarAtuais(id,sensores) {
    const area=document.getElementById(id); if(!area)return;area.replaceChildren();
    if(!sensores.length){area.append(elemento('p','Nenhum sensor com acesso autorizado para este filtro.','estado-vazio'));return;}
    sensores.forEach(s=>{
        const box=elemento('article',undefined,'sensor-atual');box.append(elemento('h3',s.nome),elemento('p',({temperatura:'Temperatura',umidade:'Umidade',qualidade_ar:'Qualidade do ar',ruido:'Ruído',pressao:'Pressão',uv:'Índice UV'}[s.tipo]||s.tipo)+' · '+s.estoque_nome,'texto-auxiliar'));
        box.append(elemento('strong',s.ultimo_valor===null?'Sem leitura':Number(s.ultimo_valor).toLocaleString('pt-BR')+' '+s.unidade,'valor-atual'));
        const online=s.comunicacao==='online';
        box.append(elemento('span',rotulosAmbientais[s.comunicacao]||s.comunicacao,'badge badge-'+s.comunicacao));
        if(s.ultimo_valor!==null)box.append(elemento('span',(online?'':'Última condição: ')+(rotulosAmbientais[s.situacao_ambiental]||'Sem classificação'),'badge badge-'+(s.situacao_ambiental||'info')));
        box.append(elemento('p',s.lido_em?('Leitura atual: '+dataBr(s.lido_em)+(online?'':' · desatualizada')):'Aguardando a primeira leitura.','texto-auxiliar'));
        const limites=elemento('p',undefined,'texto-auxiliar');
        limites.textContent=['atencao','critico'].map(n=>rotulosAmbientais[n]+': '+(s['limite_'+n+'_min']??'—')+' a '+(s['limite_'+n+'_max']??'—')+' '+s.unidade).join(' · ');box.append(limites);area.append(box);
    });
}
function dadosGraficoAmbiental(pontos) {
    const labels=[...new Set(pontos.map(p=>p.lido_em))].sort();
    const grupos=new Map();pontos.forEach(p=>{const k=p.sensor_id+':'+p.estoque_id;if(!grupos.has(k))grupos.set(k,[]);grupos.get(k).push(p);});
    const paleta=paletaDoTema();const datasets=[];
    grupos.forEach((ps,k)=>{
        const mapa=new Map(ps.map(p=>[p.lido_em,p]));const nome=ps[0].sensor_nome+' · estoque '+ps[0].estoque_id;const unidade=ps[0].unidade||'';
        datasets.push({label:nome+' ('+unidade+')',amostras:labels.map(t=>mapa.get(t)?.amostras||0),inicios:labels.map(t=>mapa.get(t)?.inicio_intervalo||t),data:labels.map(t=>mapa.has(t)?Number(mapa.get(t).valor):null),borderColor:[paleta.linha,paleta.info,paleta.atencao,paleta.critico][grupos.size===1?0:datasets.filter(d=>!d.borderDash).length%4],backgroundColor:paleta.area,pointRadius:ps.length>60?0:2,borderWidth:2,tension:0,spanGaps:true});
        ['atencao_min','atencao_max','critico_min','critico_max'].forEach(c=>{
            if(!ps.some(p=>p['limite_'+c]!==null&&p['limite_'+c]!==undefined))return;
            datasets.push({label:nome+' · '+(c.startsWith('critico')?'Crítico':'Atenção')+' '+(c.endsWith('min')?'inferior':'superior'),data:labels.map(t=>mapa.has(t)?mapa.get(t)['limite_'+c]:null),borderColor:c.startsWith('critico')?paleta.critico:paleta.atencao,borderWidth:1,borderDash:[5,5],pointRadius:0,spanGaps:true});
        });
    });return {labels,datasets};
}
function criarGraficoAmbiental(id,pontos) {
    const canvas=document.getElementById(id);if(!canvas||typeof Chart==='undefined')return null;
    const p=paletaDoTema();
    const grafico=new Chart(canvas,{type:'line',data:dadosGraficoAmbiental(pontos),options:{responsive:true,maintainAspectRatio:false,animation:false,interaction:{mode:'nearest',intersect:false},plugins:{tooltip:{callbacks:{title:items=>dataBr(items[0]?.label),label:ctx=>(ctx.dataset.amostras?(ctx.dataset.amostras[ctx.dataIndex]>1?'Média · ':'Leitura · '):'')+ctx.dataset.label+': '+Number(ctx.raw).toLocaleString('pt-BR'),footer:items=>{const c=items[0],n=c?.dataset.amostras?.[c.dataIndex];return n>1?n+' leituras · desde '+dataBr(c.dataset.inicios[c.dataIndex]):'';}}},legend:{position:'bottom',labels:{color:p.texto,font:{size:11},boxWidth:16}}},scales:{x:{ticks:{color:p.texto,maxTicksLimit:8,maxRotation:0,callback:function(v){return formatarRotulo(this.getLabelForValue(v));}},grid:{color:p.grade}},y:{ticks:{color:p.texto},grid:{color:p.grade}}}}});
    grafico.$pontosAmbientais=pontos;return grafico;
}
function atualizarGraficoAmbiental(g,pontos){if(!g)return;g.$pontosAmbientais=pontos;g.data=dadosGraficoAmbiental(pontos);g.update('none');}
async function consultarJson(url){const r=await fetch(url,{headers:{'X-Requested-With':'XMLHttpRequest'},signal:AbortSignal.timeout(12000)});if(r.status===401)throw new Error('Sessão expirada. Entre novamente.');const d=await r.json();if(!r.ok||d.sucesso===false)throw new Error(d.mensagem||d.erro||'Falha ao carregar dados.');return d;}
function iniciarMonitoramento(inicial,url) {
    const graficos={},estado=document.getElementById('monitor-status');let ocupado=false;
    const nomes={temperatura:'Temperatura',umidade:'Umidade',qualidade_ar:'Qualidade do ar',ruido:'Ruído',pressao:'Pressão',uv:'Índice UV'};
    function apresentar(d){
        mostrarAtuais('monitor-atuais',d.atuais||[]);
        const area=document.getElementById('monitor-graficos');
        Object.entries(d.series||{}).forEach(([tipo,s])=>{
            if(!graficos[tipo]){const sec=elemento('section',undefined,'card');sec.dataset.tipo=tipo;sec.append(elemento('h2',nomes[tipo]+' · histórico'));const g=elemento('div',undefined,'grafico-area');const cv=elemento('canvas');cv.id='monitor-'+tipo;cv.setAttribute('aria-label','Histórico de '+nomes[tipo]);g.append(cv);sec.append(g,elemento('p','Médias por intervalo. Linhas tracejadas: limites de atenção e criticidade.'));area.append(sec);graficos[tipo]=criarGraficoAmbiental(cv.id,s.pontos);}
            else atualizarGraficoAmbiental(graficos[tipo],s.pontos);
        });
        area.querySelectorAll('[data-tipo]').forEach(el=>el.hidden=!d.series?.[el.dataset.tipo]);
        document.getElementById('monitor-total').textContent=d.total?d.total+' leituras no período.':'Nenhuma leitura no período selecionado.';
        estado.textContent=d.erro||'Atualização automática ativa. Valores atuais mostram a última leitura recebida; gráficos mostram médias do período.';estado.className=d.erro?'msg-erro':'texto-auxiliar';
    }
    apresentar(inicial);
    setInterval(async()=>{if(ocupado||document.hidden)return;ocupado=true;estado.textContent='Atualizando leituras…';try{apresentar(await consultarJson(url));}catch(e){estado.textContent=e.message+' Dados mantidos da última consulta.';estado.className='aviso-conexao';}finally{ocupado=false;}},15000);
}
function iniciarPainel(inicial,series,url,baseGrafico,estoqueId){
    const charts={};Object.entries(series).forEach(([tipo,s])=>charts[tipo]=criarGraficoAmbiental('painel-'+tipo,s.pontos||[]));
    const barras=criarGraficoBarras('painel-alertas-grafico',[],[],'Alertas','Alertas por dia',[]);let ocupado=false;
    function apresentar(r){['estoques_total','sensores_online','alertas_pendentes','estoques_irregulares'].forEach(k=>document.getElementById('stat-'+k).textContent=r[k]);document.getElementById('painel-ultima').textContent=r.ultima_atualizacao?dataBr(r.ultima_atualizacao):'Nenhuma leitura recebida';mostrarAtuais('painel-atuais',r.atuais||[]);
        const area=document.getElementById('painel-alertas');area.replaceChildren();if(!r.alertas_recentes.length)area.append(elemento('p','Nenhum alerta encontrado.','estado-vazio'));
        r.alertas_recentes.forEach(a=>{const item=elemento('article',undefined,'alerta-resumo');item.append(elemento('span',rotulosAmbientais[a.severidade],'badge badge-'+a.severidade),elemento('strong',a.mensagem),elemento('p',a.estoque_nome+' · '+a.sensor_nome+' · '+dataBr(a.criado_em)+' · '+rotulosAmbientais[a.status],'texto-auxiliar'));area.append(item);});
        if(barras){barras.data.labels=r.alertas_periodo.map(a=>formatarRotulo(a.dia));barras.data.datasets[0].data=r.alertas_periodo.map(a=>Number(a.total));barras.$akronCores=r.alertas_periodo.map(()=> 'info');aplicarPaleta(barras);barras.update('none');}}
    apresentar(inicial);
    setInterval(async()=>{if(ocupado||document.hidden)return;ocupado=true;const status=document.getElementById('painel-status');status.textContent='Atualizando monitoramento…';try{const [d,...ss]=await Promise.all([consultarJson(url),...Object.keys(charts).map(t=>consultarJson(baseGrafico+'?'+new URLSearchParams({sensor:t,horas:24,estoque_id:estoqueId})))]);apresentar(d.resumo);Object.keys(charts).forEach((t,i)=>{atualizarGraficoAmbiental(charts[t],ss[i].pontos);document.getElementById('vazio-'+t).textContent=ss[i].total?'Médias por intervalo, separadas por sensor.':'Nenhuma leitura no período.';});status.textContent='Dados atualizados automaticamente.';status.className='texto-auxiliar';}catch(e){status.textContent=e.message+' Dados mantidos da última consulta.';status.className='aviso-conexao';}finally{ocupado=false;}},15000);
}

document.addEventListener('akron:tema',()=>{if(typeof Chart==='undefined')return;const p=paletaDoTema();Object.values(Chart.instances).forEach(g=>{if(!g.$pontosAmbientais)return;g.data=dadosGraficoAmbiental(g.$pontosAmbientais);g.options.plugins.legend.labels.color=p.texto;['x','y'].forEach(a=>{g.options.scales[a].ticks.color=p.texto;g.options.scales[a].grid.color=p.grade;});g.update('none');});});
