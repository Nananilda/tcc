/* Apresentação comum do monitoramento (painel, monitoramento, detalhes, relatórios).
 * Dados do servidor sempre tratados como texto (textContent); nada é interpolado em HTML.
 * Gráficos: eixo X em tempo real (milissegundos locais, sem conversão de fuso), de modo que
 * períodos sem leituras aparecem como vazios — pontos separados por lacuna NUNCA são ligados. */
const rotulosAmbientais = { normal:'Normal', atencao:'Atenção', critico:'Crítico', info:'Informativo', online:'Online', sem_comunicacao:'Sem comunicação', inativo:'Inativo', pendente:'Pendente', reconhecido:'Reconhecido', normalizado:'Normalizado', resolvido:'Resolvido' };
const rotulosTipo = { temperatura:'Temperatura', umidade:'Umidade', qualidade_ar:'Qualidade do ar', ruido:'Ruído', pressao:'Pressão', uv:'Índice UV' };
const rotulosOrigem = { sensor:'Sensor/gateway', manual:'Registro manual', demonstracao:'Demonstração', nao_informada:'Origem não informada' };
function elemento(tag, texto, classe) { const el=document.createElement(tag); if(texto!==undefined)el.textContent=texto; if(classe)el.className=classe;return el; }
function numeroBr(v){ return Number(v).toLocaleString('pt-BR'); }

/* ── Valores atuais ─────────────────────────────────────────────────────────── */
function mostrarAtuais(id,sensores) {
    const area=document.getElementById(id); if(!area)return;area.replaceChildren();
    if(!sensores.length){area.append(elemento('p','Nenhum sensor com acesso autorizado para este filtro.','estado-vazio'));return;}
    sensores.forEach(s=>{
        const box=elemento('article',undefined,'sensor-atual');box.append(elemento('h3',s.nome),elemento('p',(rotulosTipo[s.tipo]||s.tipo)+' · '+s.estoque_nome,'texto-auxiliar'));
        box.append(elemento('strong',s.ultimo_valor===null?'Sem leitura':numeroBr(s.ultimo_valor)+' '+s.unidade,'valor-atual'));
        const online=s.comunicacao==='online';
        box.append(elemento('span',rotulosAmbientais[s.comunicacao]||s.comunicacao,'badge badge-'+s.comunicacao));
        if(s.ultimo_valor!==null)box.append(elemento('span',(online?'':'Última condição: ')+(rotulosAmbientais[s.situacao_ambiental]||'Sem classificação'),'badge badge-'+(s.situacao_ambiental||'info')));
        box.append(elemento('p',s.lido_em?('Leitura atual: '+dataBr(s.lido_em)+(online?'':' · desatualizada')):'Aguardando a primeira leitura.','texto-auxiliar'));
        if(s.lido_em)box.append(elemento('p','Origem do dado: '+(rotulosOrigem[s.ultima_origem]||rotulosOrigem.nao_informada),'texto-auxiliar'));
        const limites=elemento('p',undefined,'texto-auxiliar');
        limites.textContent=['atencao','critico'].map(n=>rotulosAmbientais[n]+': '+(s['limite_'+n+'_min']??'—')+' a '+(s['limite_'+n+'_max']??'—')+' '+s.unidade).join(' · ');box.append(limites);area.append(box);
    });
}

/* ── Séries temporais ───────────────────────────────────────────────────────── */
/** '2026-11-03 14:30:00' → milissegundos no horário local do texto (sem deslocar fuso). */
function tempoMs(texto){const m=String(texto||'').match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);return m?new Date(+m[1],+m[2]-1,+m[3],+(m[4]||0),+(m[5]||0),+(m[6]||0)).getTime():NaN;}
/** Aceita a série completa ({pontos,lacunas,janela}) ou apenas a lista de pontos (clientes antigos). */
function normalizarSerie(s){return Array.isArray(s)?{pontos:s,lacunas:[],janela:null}:{pontos:s?.pontos||[],lacunas:s?.lacunas||[],janela:s?.janela||null};}

function dadosGraficoAmbiental(entrada) {
    const serie=normalizarSerie(entrada),pontos=serie.pontos;
    const grupos=new Map();pontos.forEach(p=>{const k=p.sensor_id+':'+p.estoque_id;if(!grupos.has(k))grupos.set(k,[]);grupos.get(k).push(p);});
    const paleta=paletaDoTema(),cores=[paleta.linha,paleta.info,paleta.atencao,paleta.critico];const datasets=[];let n=0;
    grupos.forEach(ps=>{
        ps.sort((a,b)=>String(a.lido_em).localeCompare(String(b.lido_em)));
        const nome=ps[0].sensor_nome+' · estoque '+ps[0].estoque_id,unidade=ps[0].unidade||'',cor=cores[(grupos.size===1?0:n)%4];n++;
        /* Um ponto nulo entre dois pontos separados por lacuna interrompe a linha. */
        const construir=campo=>{const out=[];ps.forEach((p,i)=>{const x=tempoMs(p.lido_em);if(i>0&&p.lacuna_antes){out.push({x:(tempoMs(ps[i-1].lido_em)+x)/2,y:null});}out.push(campo(p,x));});return out;};
        datasets.push({label:nome+' ('+unidade+')',unidade,data:construir((p,x)=>({x,y:Number(p.valor),t:p.lido_em,ini:p.inicio_intervalo,n:p.amostras})),borderColor:cor,backgroundColor:paleta.area,borderWidth:2,tension:0,spanGaps:false,
            pointRadius:c=>{const d=c.dataset.data;if(d.length<=60)return 3;const i=c.dataIndex;const isolado=(i===0||d[i-1].y===null)&&(i===d.length-1||d[i+1].y===null);return isolado?3:0;},
            pointHoverRadius:5,pointStyle:c=>(c.raw&&c.raw.n>1)?'rectRot':'circle'});
        ['atencao_min','atencao_max','critico_min','critico_max'].forEach(c=>{
            if(!ps.some(p=>p['limite_'+c]!==null&&p['limite_'+c]!==undefined))return;
            datasets.push({limite:true,label:nome+' · '+(c.startsWith('critico')?'Crítico':'Atenção')+' '+(c.endsWith('min')?'inferior':'superior'),
                data:construir((p,x)=>({x,y:p['limite_'+c]===null||p['limite_'+c]===undefined?null:Number(p['limite_'+c]),t:p.lido_em})),
                borderColor:c.startsWith('critico')?paleta.critico:paleta.atencao,borderWidth:1,borderDash:[5,5],pointRadius:0,pointHoverRadius:0,spanGaps:false});
        });
    });
    return {datasets};
}

/** Faixas hachuradas nos períodos sem leituras (comunicação interrompida ou dado desatualizado). */
const pluginLacunas={id:'akronLacunas',beforeDatasetsDraw(chart){
    const lacunas=chart.$serie?.lacunas||[];if(!lacunas.length)return;const {ctx,chartArea:a,scales:{x}}=chart;if(!a||!x)return;
    const p=paletaDoTema();ctx.save();ctx.beginPath();ctx.rect(a.left,a.top,a.right-a.left,a.bottom-a.top);ctx.clip();
    const padrao=document.createElement('canvas');padrao.width=padrao.height=8;const g=padrao.getContext('2d');g.strokeStyle=p.texto;g.globalAlpha=.28;g.lineWidth=1;g.beginPath();g.moveTo(0,8);g.lineTo(8,0);g.stroke();
    ctx.fillStyle=ctx.createPattern(padrao,'repeat');ctx.font='11px sans-serif';
    lacunas.forEach(l=>{const x0=Math.max(a.left,x.getPixelForValue(tempoMs(l.de))),x1=Math.min(a.right,x.getPixelForValue(tempoMs(l.ate)));if(!(x1>x0))return;
        ctx.fillRect(x0,a.top,x1-x0,a.bottom-a.top);ctx.strokeStyle=p.grade;ctx.setLineDash([4,3]);ctx.strokeRect(x0,a.top,x1-x0,a.bottom-a.top);ctx.setLineDash([]);
        if(x1-x0>96){ctx.fillStyle=p.texto;ctx.textAlign='center';ctx.fillText(l.aberta?'Sem leituras recentes':'Sem leituras',(x0+x1)/2,a.top+14);ctx.fillStyle=ctx.createPattern(padrao,'repeat');}});
    ctx.restore();}};

/** Eixo: somente datas (dia a dia); o horário exato aparece ao passar o mouse sobre o ponto. */
function configurarEixoX(serie){
    const dia=864e5,j=serie.janela;let min,max;
    const todos=serie.pontos.map(p=>tempoMs(p.inicio_intervalo||p.lido_em)).concat(serie.pontos.map(p=>tempoMs(p.lido_em))).filter(Number.isFinite);
    min=j?tempoMs(j.inicio):Math.min(...todos);max=j?tempoMs(j.fim):Math.max(...todos);
    if(todos.length){min=Math.min(min,...todos);}if(!(max>min))max=min+3600000;
    return {type:'linear',min,max,ticks:{maxRotation:0,autoSkip:false,callback(v,i,ticks){const r=dataBr(new Date(v),false);return (i>0&&dataBr(new Date(ticks[i-1].value),false)===r)?'':r;}},
        afterBuildTicks(escala){const t=[];if(escala.max-escala.min>=1.5*dia){let d=new Date(escala.min);d.setHours(0,0,0,0);if(d.getTime()<escala.min)d.setDate(d.getDate()+1);while(d.getTime()<=escala.max){t.push({value:d.getTime()});d.setDate(d.getDate()+1);}
            const limite=Math.max(2,Math.floor((escala.width||600)/110)),passo=Math.max(1,Math.ceil(t.length/limite));escala.ticks=t.filter((_,i)=>i%passo===0);}else{for(let i=0;i<=Math.max(1,Math.min(4,Math.floor((escala.width||600)/110)-1));i++)t.push({value:escala.min+(escala.max-escala.min)*i/Math.max(1,Math.min(4,Math.floor((escala.width||600)/110)-1))});escala.ticks=t;}}};
}
function criarGraficoAmbiental(id,entrada) {
    const canvas=document.getElementById(id);if(!canvas||typeof Chart==='undefined')return null;
    const p=paletaDoTema(),serie=normalizarSerie(entrada),unidade=serie.pontos[0]?.unidade||'';const x=configurarEixoX(serie);
    x.ticks.color=p.texto;x.grid={color:p.grade};
    const grafico=new Chart(canvas,{type:'line',data:dadosGraficoAmbiental(serie),plugins:[pluginLacunas],options:{responsive:true,maintainAspectRatio:false,animation:false,interaction:{mode:'nearest',intersect:false},
        plugins:{tooltip:{filter:i=>!i.dataset.limite,callbacks:{
            title:items=>items[0]?dataBr(items[0].raw.t):'',
            label:ctx=>{const n=ctx.raw.n;return (n>1?'Média de '+n+' leituras · ':'Leitura individual · ')+ctx.dataset.label.replace(/ \(.*\)$/,'')+': '+numeroBr(ctx.raw.y)+' '+(ctx.dataset.unidade||'');},
            footer:items=>{const r=items[0]?.raw;return r&&r.n>1?'Período: '+dataBr(r.ini)+' a '+dataBr(r.t):'';}}},
            legend:{position:'top',align:'start',labels:{color:p.texto,font:{size:11},boxWidth:14,filter:(item,d)=>!d.datasets[item.datasetIndex].limite}}},
        scales:{x,y:{ticks:{color:p.texto},grid:{color:p.grade},title:{display:!!unidade,text:unidade,color:p.texto}}}}});
    grafico.$serie=serie;grafico.$pontosAmbientais=serie.pontos;return grafico;
}
function atualizarGraficoAmbiental(g,entrada){if(!g)return;const serie=normalizarSerie(entrada);g.$serie=serie;g.$pontosAmbientais=serie.pontos;g.data=dadosGraficoAmbiental(serie);
    const x=configurarEixoX(serie);g.options.scales.x.min=x.min;g.options.scales.x.max=x.max;g.update('none');}
async function consultarJson(url){const r=await fetch(url,{headers:{'X-Requested-With':'XMLHttpRequest'},signal:AbortSignal.timeout(12000)});if(r.status===401)throw new Error('Sessão expirada. Entre novamente.');const d=await r.json();if(!r.ok||d.sucesso===false)throw new Error(d.mensagem||d.erro||'Falha ao carregar dados.');return d;}

/* ── Preferências de exibição (só conveniência; protegidas contra armazenamento indisponível) ── */
const preferencia={ler(k,reserva){try{const v=localStorage.getItem('akron:'+k);return v===null?reserva:JSON.parse(v);}catch(e){return reserva;}},gravar(k,v){try{localStorage.setItem('akron:'+k,JSON.stringify(v));}catch(e){}}};

/* ── Página de monitoramento ────────────────────────────────────────────────── */
function iniciarMonitoramento(inicial,url) {
    const graficos={},estado=document.getElementById('monitor-status'),area=document.getElementById('monitor-graficos'),controles=document.getElementById('monitor-controles');let ocupado=false;
    let visiveis=preferencia.ler('monitor:tipos',null),recolhidos=preferencia.ler('monitor:recolhidos',null),primeiraVez=true;
    function sincronizar(){area.querySelectorAll('[data-tipo]').forEach(sec=>{sec.hidden=!(visiveis===null||visiveis.includes(sec.dataset.tipo))||sec.dataset.semDados==='1';});
        controles?.querySelectorAll('input[data-tipo]').forEach(c=>{c.checked=visiveis===null||visiveis.includes(c.dataset.tipo);});}
    function construirControles(tipos){
        if(!controles||controles.dataset.pronto===tipos.join(','))return;controles.dataset.pronto=tipos.join(',');controles.replaceChildren();
        const grupo=elemento('fieldset',undefined,'tipos-medicao');grupo.append(elemento('legend','Tipos de medição exibidos'));
        tipos.forEach(t=>{const l=elemento('label',undefined,'chip-tipo'),c=document.createElement('input');c.type='checkbox';c.dataset.tipo=t;
            c.addEventListener('change',()=>{const atuais=tipos.filter(x=>controles.querySelector('input[data-tipo="'+x+'"]').checked);visiveis=atuais;preferencia.gravar('monitor:tipos',visiveis);sincronizar();});
            l.append(c,elemento('span',rotulosTipo[t]));grupo.append(l);});
        const acoes=elemento('div',undefined,'acoes-grafico');
        [['Expandir todos',true],['Recolher todos',false]].forEach(([texto,abrir])=>{const b=elemento('button',texto,'btn-secundario');b.type='button';b.addEventListener('click',()=>{area.querySelectorAll('details[data-tipo]').forEach(d=>d.open=abrir);salvarRecolhidos();});acoes.append(b);});
        controles.append(grupo,acoes);
    }
    function salvarRecolhidos(){recolhidos=[...area.querySelectorAll('details[data-tipo]')].filter(d=>!d.open).map(d=>d.dataset.tipo);preferencia.gravar('monitor:recolhidos',recolhidos);}
    function apresentar(d){
        mostrarAtuais('monitor-atuais',d.atuais||[]);
        const tipos=Object.keys(d.series||{});construirControles(tipos);
        tipos.forEach((tipo,i)=>{const s=d.series[tipo];
            if(!graficos[tipo]){
                const sec=elemento('details',undefined,'card grafico-recolhivel');sec.dataset.tipo=tipo;
                /* Padrão: só o primeiro gráfico aberto; a escolha do usuário passa a valer nas visitas seguintes. */
                sec.open=recolhidos===null?i===0:!recolhidos.includes(tipo);sec.addEventListener('toggle',salvarRecolhidos);
                const sum=elemento('summary'),tit=elemento('h2',rotulosTipo[tipo]+' ('+(s.pontos[0]?.unidade||'')+')');sum.append(tit,elemento('span',undefined,'resumo-grafico'));sec.append(sum);
                const g=elemento('div',undefined,'grafico-area'),cv=elemento('canvas');cv.id='monitor-'+tipo;cv.setAttribute('role','img');cv.setAttribute('aria-label','Histórico de '+rotulosTipo[tipo]);g.append(cv);
                sec.append(g,elemento('p','Pontos redondos: leituras individuais. Losangos: média de um intervalo. Faixas hachuradas: períodos sem leituras. Linhas tracejadas: limites de atenção e criticidade. Passe o mouse sobre um ponto para ver o horário exato.','texto-auxiliar nota-grafico'));
                area.append(sec);graficos[tipo]=criarGraficoAmbiental(cv.id,s);
            } else atualizarGraficoAmbiental(graficos[tipo],s);
            const sensores=new Set(s.pontos.map(p=>p.sensor_id)).size,ult=s.pontos.reduce((m,p)=>String(p.lido_em)>m?String(p.lido_em):m,'');
            const sec=area.querySelector('[data-tipo="'+tipo+'"]'),resumo=sec.querySelector('.resumo-grafico');
            resumo.textContent=sensores+(sensores===1?' sensor · ':' sensores · ')+s.total+' leituras · última: '+dataBr(ult)+((s.sensores_desatualizados||[]).length?' · há sensor sem leituras recentes':'');
            sec.dataset.semDados='0';});
        area.querySelectorAll('[data-tipo]').forEach(el=>{if(!d.series?.[el.dataset.tipo])el.dataset.semDados='1';});
        sincronizar();
        document.getElementById('monitor-total').textContent=d.total?d.total+' leituras no período.':'Nenhuma leitura no período selecionado.';
        estado.textContent=d.erro||'Atualização automática ativa. Valores atuais mostram a última leitura recebida; gráficos mostram leituras e médias do período.';estado.className=d.erro?'msg-erro':'texto-auxiliar';primeiraVez=false;
    }
    apresentar(inicial);
    setInterval(async()=>{if(ocupado||document.hidden)return;ocupado=true;estado.textContent='Atualizando leituras…';try{apresentar(await consultarJson(url));}catch(e){estado.textContent=e.message+' Dados mantidos da última consulta.';estado.className='aviso-conexao';}finally{ocupado=false;}},15000);
}

document.addEventListener('akron:tema',()=>{if(typeof Chart==='undefined')return;const p=paletaDoTema();Object.values(Chart.instances).forEach(g=>{if(!g.$serie)return;g.data=dadosGraficoAmbiental(g.$serie);g.options.plugins.legend.labels.color=p.texto;['x','y'].forEach(a=>{g.options.scales[a].ticks.color=p.texto;g.options.scales[a].grid.color=p.grade;});if(g.options.scales.y.title)g.options.scales.y.title.color=p.texto;g.update('none');});});
