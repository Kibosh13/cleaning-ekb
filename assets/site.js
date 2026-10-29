(()=>{'use strict';
const $=(s,r=document)=>r.querySelector(s),$$=(s,r=document)=>[...r.querySelectorAll(s)];
const preview=document.body.dataset.preview==='true';
const menu=$('.menu-toggle'),nav=$('#mobile-nav');
menu?.addEventListener('click',()=>{const open=menu.getAttribute('aria-expanded')!=='true';menu.setAttribute('aria-expanded',String(open));menu.setAttribute('aria-label',open?'Закрыть меню':'Открыть меню');nav.hidden=!open;$('.header')?.classList.toggle('menu-open',open);});
$$('a',nav||document.createElement('nav')).forEach(a=>a.addEventListener('click',()=>{nav.hidden=true;$('.header')?.classList.remove('menu-open');menu.setAttribute('aria-expanded','false');menu.setAttribute('aria-label','Открыть меню');}));
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&menu){nav.hidden=true;$('.header')?.classList.remove('menu-open');menu.setAttribute('aria-expanded','false');}});
const banner=$('.cookie-banner');let consent='essential';
try{consent=localStorage.getItem('chistota-cookie-v1')||'';}catch{}
function metrics(){const id=Number(document.body.dataset.metrika);if(!id||window.ym||consent!=='all')return;window.ym=function(){(window.ym.a=window.ym.a||[]).push(arguments)};window.ym.l=Date.now();const script=document.createElement('script');script.src='https://mc.yandex.ru/metrika/tag.js';script.async=true;document.head.append(script);window.ym(id,'init',{clickmap:true,trackLinks:true,accurateTrackBounce:true,webvisor:false});}
function goal(name){const id=Number(document.body.dataset.metrika);if(consent==='all'&&id&&window.ym)window.ym(id,'reachGoal',name);}
if(banner)banner.hidden=!!consent;
if(consent==='all')metrics();
$$('[data-cookie]').forEach(b=>b.addEventListener('click',()=>{const previous=consent;consent=b.dataset.cookie;try{localStorage.setItem('chistota-cookie-v1',consent);}catch{}banner.hidden=true;if(previous==='all'&&consent!=='all'){location.reload();return;}metrics();}));
$('.cookie-settings')?.addEventListener('click',()=>{banner.hidden=false;$('[data-cookie]',banner)?.focus();});
$$('.main-action').forEach(a=>a.addEventListener('click',()=>goal('request_click')));
let services=[];try{services=JSON.parse($('#services-data')?.textContent||'[]')}catch{}
function servicePrice(service){return service.price.includes('[заполнить')?'Стоимость по запросу':`от ${service.price} ${service.price_unit||'₽'}${service.price_context?' · '+service.price_context:''}`;}
function node(tag,text,className){const element=document.createElement(tag);if(text!==undefined)element.textContent=text;if(className)element.className=className;return element;}
function priceTable(data){
 const wrap=node('div',undefined,'price-table-wrap'),table=node('table',undefined,'price-table');
 table.append(node('caption',data.title));
 const head=node('thead'),headRow=node('tr');
 data.columns.forEach(text=>{const cell=node('th',text);cell.scope='col';headRow.append(cell);});head.append(headRow);table.append(head);
 const body=node('tbody');data.rows.forEach(row=>{const tr=node('tr');row.forEach((text,index)=>{const cell=node(index===0?'th':'td',text);if(index===0)cell.scope='row';tr.append(cell);});body.append(tr);});
 table.append(body);wrap.append(table);return wrap;
}
// Выбор услуги меняет крупное фото и описание, не сбрасывая фильтр.
let featureRequest=0;
function selectFeature(id){
 const service=services.find(s=>s.id===id);if(!service)return;
 $$('.service-select').forEach(b=>{const active=b.dataset.feature===id;b.setAttribute('aria-pressed',String(active));b.closest('.service-card').classList.toggle('is-selected',active);});
 const image=$('#service-feature-image'),request=++featureRequest;
 if(image){
   image.classList.add('is-changing');
   const loaded=new Image();loaded.src=service.image;
   const finish=()=>{if(request!==featureRequest)return;image.src=service.image;image.alt=service.title+' — иллюстрация интерьера';requestAnimationFrame(()=>image.classList.remove('is-changing'));};
   if(loaded.complete)finish();else{loaded.onload=finish;loaded.onerror=()=>image.classList.remove('is-changing');}
 }
 $('#service-feature-title').textContent=service.title;
 $('#service-feature-description').textContent=service.description;
 const detail=$('.service-showcase .service-detail');detail.dataset.service=id;detail.setAttribute('aria-label','Подробнее: '+service.title);
}
$$('[data-feature]').forEach(button=>button.addEventListener('click',()=>selectFeature(button.dataset.feature)));
$$('[data-filter]').forEach(button=>button.addEventListener('click',()=>{
 const filter=button.dataset.filter;$$('[data-filter]').forEach(b=>{const active=b===button;b.classList.toggle('active',active);b.setAttribute('aria-pressed',String(active));});
 let count=0,first='';$$('.service-card').forEach(card=>{card.hidden=filter!=='all'&&!card.dataset.group.split(' ').includes(filter);if(!card.hidden){count++;first||=card.querySelector('[data-feature]').dataset.feature;}});
 const word=count===1?'услуга':count>=2&&count<=4?'услуги':'услуг';$('.service-count').textContent=`${count} ${word}`;
 if(first)selectFeature(first);
}));
const dialog=$('#service-dialog');let activeService='';
$$('.service-detail').forEach(button=>button.addEventListener('click',()=>{
 const service=services.find(item=>item.id===button.dataset.service);if(!service)return;activeService=service.id;
 $('#dialog-title').textContent=service.title;$('#dialog-description').textContent=service.description;
 const sections=[];
 if(service.includes.length)sections.push({title:'Что входит в уборку',items:service.includes});
 sections.push(...(service.sections||[]));
 $('#dialog-sections').replaceChildren(...sections.map(section=>{const block=node('section',undefined,'service-work-section');block.append(node('h3',section.title));const list=node('ul');section.items.forEach(text=>list.append(node('li',text)));block.append(list);return block;}));
 $('#dialog-pricing').replaceChildren(...(service.pricing||[]).map(priceTable));
 $('#dialog-note').textContent=service.note;$('#dialog-note').hidden=!service.note||service.note.includes('[заполнить');
 $('#dialog-price').textContent=servicePrice(service);
 dialog.setAttribute('aria-labelledby','dialog-title');dialog.showModal();document.body.classList.add('modal-open');
}));
function closeDialog(){dialog?.close();document.body.classList.remove('modal-open');}
$('.dialog-close')?.addEventListener('click',closeDialog);dialog?.addEventListener('close',()=>document.body.classList.remove('modal-open'));dialog?.addEventListener('click',e=>{const r=dialog.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)closeDialog();});
function choose(id){const select=$('#lead-service');if(select&&[...select.options].some(o=>o.value===id)){select.value=id;select.dispatchEvent(new Event('change'));}}
$('#dialog-choose')?.addEventListener('click',()=>{choose(activeService);closeDialog();});
$('#dialog-extras')?.addEventListener('click',closeDialog);
$$('[data-select]').forEach(a=>a.addEventListener('click',()=>choose(a.dataset.select)));
const reduceMotion=window.matchMedia('(prefers-reduced-motion: reduce)');
const hero=$('.hero-fullscreen'),header=$('.header');
let scrollScheduled=false;
function updateScroll(){
 scrollScheduled=false;const y=window.scrollY;
 header?.classList.toggle('is-scrolled',y>40);
 if(hero&&!reduceMotion.matches){const shift=Math.min(y,hero.offsetHeight)*.12;hero.style.setProperty('--hero-shift',`${shift}px`);}
}
window.addEventListener('scroll',()=>{if(!scrollScheduled){scrollScheduled=true;requestAnimationFrame(updateScroll);}},{passive:true});
reduceMotion.addEventListener('change',()=>{if(reduceMotion.matches){document.documentElement.classList.remove('motion-ready');hero?.style.removeProperty('--hero-shift');}else updateScroll();});
updateScroll();
if('IntersectionObserver' in window&&!reduceMotion.matches){
 const elements=$$('[data-reveal]');
 document.documentElement.classList.add('motion-ready');
 const observer=new IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.isIntersecting){entry.target.classList.add('is-visible');observer.unobserve(entry.target);}}),{threshold:.08,rootMargin:'0px 0px -24px 0px'});
 elements.forEach((element,index)=>{element.style.setProperty('--reveal-delay',`${(index%3)*70}ms`);observer.observe(element);});
}
const form=$('#lead-form');if(!form)return;
function premisesRequirements(){
 const gift=$('#lead-service').value==='gift-certificate';
 $('#premises-fields').hidden=gift;
 ['#lead-property','#lead-area'].forEach(selector=>{$(selector).required=!gift;$(selector).disabled=gift;});
 $('#lead-time').disabled=gift;
}
$('#lead-service').addEventListener('change',premisesRequirements);premisesRequirements();
const phone=$('#lead-phone');phone.addEventListener('input',()=>phone.setCustomValidity(''));
let contacts={};try{contacts=JSON.parse($('#contact-data').textContent)}catch{}
let csrfToken='',submitting=false;
async function token(){
 const response=await fetch('/api/token.php',{credentials:'same-origin',cache:'no-store'});
 if(!response.ok)throw Error('Не удалось подготовить форму.');
 const data=await response.json();
 if(typeof data.token!=='string'||!data.token)throw Error('Не удалось подготовить форму.');
 csrfToken=data.token;$('#lead-csrf').value=csrfToken;
}
async function sendBooking(){
 if(!csrfToken)await token();
 const post=()=>fetch(form.action,{method:'POST',body:new FormData(form),credentials:'same-origin',headers:{'Accept':'application/json'}});
 let response=await post();
 // CSRF is checked before delivery, so only this rejection can safely be retried.
 if(response.status===403){csrfToken='';await token();response=await post();}
 const data=await response.json();
 if(!response.ok||data.ok!==true)throw Error(data.message||'Не удалось отправить заявку.');
 return data;
}
if(!preview)token().catch(()=>{});
form.addEventListener('submit',async event=>{
 event.preventDefault();if(submitting)return;
 const digits=phone.value.replace(/\D/g,'');
 if(digits.length<10||digits.length>15){phone.setCustomValidity('Введите номер телефона: от 10 до 15 цифр.');phone.reportValidity();return;}
 phone.setCustomValidity('');if(!form.reportValidity())return;
 const status=$('.form-status',form),button=$('button[type=submit]',form);
 status.className='form-status';status.textContent='';
 if(preview){status.textContent='Это демоверсия: отправка заявок пока не подключена. На рабочем сайте заявка будет отправляться этой кнопкой.';return;}
 const buttonLabel=button.textContent;
 submitting=true;button.disabled=true;button.textContent='Отправляем…';form.setAttribute('aria-busy','true');
 status.textContent='Отправляем заявку…';
 try{
  const data=await sendBooking();
  status.classList.add('success');status.textContent=data.message;
  form.reset();csrfToken='';goal('lead_sent');token().catch(()=>{});
 }catch(error){
  status.classList.add('error');status.textContent=`${error.message||'Не удалось отправить заявку.'} Вы можете позвонить: ${contacts.phone}.`;
 }finally{
  submitting=false;button.disabled=false;button.textContent=buttonLabel;form.removeAttribute('aria-busy');
 }
});
})();
