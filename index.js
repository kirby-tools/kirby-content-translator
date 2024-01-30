(function(){"use strict";const n=window.Vue;function R(){return window.panel}function O(){return R().api}function $(){const l=O();return{load:({parent:t,name:e})=>l.get(`${t}/sections/${e}`)}}function U(){var s;return((s=n.getCurrentInstance())==null?void 0:s.proxy).$store}const S=n.computed;n.customRef,n.defineAsyncComponent;const j=n.defineComponent;n.effectScope,n.getCurrentInstance,n.getCurrentScope,n.h,n.inject,n.isProxy,n.isReactive,n.isReadonly,n.isRef,n.isShallow,n.markRaw,n.nextTick,n.onActivated,n.onBeforeMount;const B=n.onBeforeUnmount;n.onBeforeUpdate,n.onDeactivated,n.onErrorCaptured,n.onMounted,n.onRenderTracked,n.onRenderTriggered,n.onScopeDispose,n.onServerPrefetch,n.onUnmounted,n.onUpdated,n.provide,n.proxyRefs,n.reactive,n.readonly;const g=n.ref;n.shallowReactive,n.shallowReadonly,n.shallowRef,n.toRaw,n.toRef,n.toRefs,n.triggerRef,n.unref,n.useAttrs,n.useCssModule,n.useCssVars,n.useListeners,n.useSlots,n.watch,n.watchEffect,n.watchPostEffect,n.watchSyncEffect;const D={blueprint:String,lock:[Boolean,Object],help:String,name:String,parent:String,timestamp:Number},b="__content-translator__/translate",E=5;function N(){const l=O();return{recursiveTranslateContent:async(t,{sourceLanguage:e,targetLanguage:p,translatableStructureFields:d=[],translatableBlocks:y={}})=>{const v=[],c=i=>{for(const o of i)for(const a in o)d.includes(a)&&o[a]&&(typeof o[a]=="string"?v.push(async()=>{const u=await l.post(b,{sourceLanguage:e,targetLanguage:p,text:o[a]});o[a]=u.result.text}):Array.isArray(o[a])&&o[a].every(u=>typeof u=="string")&&v.push(async()=>{for(const u in o[a]){if(!o[a][u])continue;const m=await l.post(b,{sourceLanguage:e,targetLanguage:p,text:o[a][u]});o[a][u]=m.result.text}}))},h=i=>{for(const o of i)if(!(!C(o.content)||!o.id||o.isHidden===!0)&&Object.keys(y).includes(o.type)){for(const a of Object.keys(o.content))if(P(y[o.type]).includes(a)&&o.content[a]){if(Array.isArray(o.content[a])&&o.content[a].every(u=>C(u)&&u.content)){h(o.content[a]);continue}if(Array.isArray(o.content[a])&&o.content[a].every(u=>C(u)&&Object.keys(u).some(m=>d.includes(m)))){c(o.content[a]);continue}v.push(async()=>{const u=await l.post(b,{sourceLanguage:e,targetLanguage:p,text:o.content[a]});o.content[a]=u.result.text})}}};for(const i in t)if(t[i]){if(typeof t[i]=="string")v.push(async()=>{const o=await l.post(b,{sourceLanguage:e,targetLanguage:p,text:t[i]});t[i]=o.result.text});else if(Array.isArray(t[i])&&t[i].length>0){if(t[i].every(o=>typeof o=="string")){t[i]=await Promise.all(t[i].filter(Boolean).map(async o=>(await l.post(b,{sourceLanguage:e,targetLanguage:p,text:o})).result.text));continue}if(t[i].every(o=>C(o)&&o.columns)){for(const o of t[i])for(const a of o.columns)h(a.blocks);continue}if(t[i].every(o=>C(o)&&o.content)&&h(t[i]),t[i].every(o=>C(o)&&Object.keys(o).some(a=>d.includes(a)))){c(t[i]);continue}}}try{await I(v)}catch(i){throw console.error(i),i}return t}}}async function I(l,s=E){for(let t=0;t<l.length;t+=s){const e=l.slice(t,t+s);await Promise.all(e.map(p=>p()))}}function P(l){return Array.isArray(l)?l:[l]}function C(l){return typeof l=="object"&&l!==null}function z(l,s,t,e,p,d,y,v){var c=typeof l=="function"?l.options:l;s&&(c.render=s,c.staticRenderFns=t,c._compiled=!0),e&&(c.functional=!0),d&&(c._scopeId="data-v-"+d);var h;if(y?(h=function(a){a=a||this.$vnode&&this.$vnode.ssrContext||this.parent&&this.parent.$vnode&&this.parent.$vnode.ssrContext,!a&&typeof __VUE_SSR_CONTEXT__<"u"&&(a=__VUE_SSR_CONTEXT__),p&&p.call(this,a),a&&a._registeredComponents&&a._registeredComponents.add(y)},c._ssrRegister=h):p&&(h=v?function(){p.call(this,(c.functional?this.parent:this).$root.$options.shadowRoot)}:p),h)if(c.functional){c._injectStyles=h;var i=c.render;c.render=function(u,m){return h.call(m),i(u,m)}}else{var o=c.beforeCreate;c.beforeCreate=o?[].concat(o,h):[h]}return{exports:l,options:c}}const K=j({inheritAttrs:!1,props:{...D}}),V=Object.assign(K,{__name:"ContentTranslator",props:{},setup(l){const s=l,t=R(),e=U(),{recursiveTranslateContent:p}=N(),d=g(),y=g(!0),v=g([]),c=g([]),h=g([]),i=g([]),o=g(!1),a=g(),u=g({}),m=t.languages.find(f=>f.default),A=S(()=>e.getters["content/values"]()),T=S(()=>Object.fromEntries(Object.entries(A.value).filter(([f])=>c.value.includes(f))));(async()=>{const{load:f}=$(),r=await f({parent:s.parent,name:s.name});d.value=M(r.label)||t.t("johannschopplich.content-translator.label"),y.value=r.confirm??r.config.confirm??!0,c.value=r.translatableFields??r.config.translatableFields??[],h.value=r.translatableStructureFields??r.config.translatableStructureFields??[],v.value=r.syncableFields??r.config.syncableFields??[],i.value=r.translatableBlocks??r.config.translatableBlocks??[],o.value=r.title??r.config.title??!1,a.value=r.config??{},t.events.on("model.update",x),x()})(),B(()=>{t.events.off("model.update",x)});function M(f){return!f||typeof f=="string"?f:f[t.translation.code]??Object.values(f)[0]}async function H(f){let{title:r,content:k}=u.value;if(f){const _=await F(f);r=_.title,k=_.content}const w=Object.fromEntries(Object.entries(k).filter(([_])=>v.value.includes(_)));for(const[_,G]of Object.entries(w))e.dispatch("content/update",[_,G]);o.value&&(await t.api.patch(`${t.view.path}/title`,{title:r}),await t.view.reload()),t.notification.success(t.t("johannschopplich.content-translator.notification.synced"))}async function Q(f,r){t.view.isLoading=!0;const k=JSON.parse(JSON.stringify(T.value));try{await p(k,{sourceLanguage:r==null?void 0:r.code,targetLanguage:f.code,translatableStructureFields:h.value,translatableBlocks:i.value})}catch(w){console.error(w),t.notification.error(t.t("error"));return}for(const[w,_]of Object.entries(k))e.dispatch("content/update",[w,_]);if(o.value){const{result:w}=await t.api.post(b,{sourceLanguage:r==null?void 0:r.code,targetLanguage:f.code,text:t.view.title});await t.api.patch(`${t.view.path}/title`,{title:w.text}),await t.view.reload()}else t.view.isLoading=!1;t.notification.success(t.t("johannschopplich.content-translator.notification.translated"))}async function x(){u.value=await F(m)}function F(f){return t.api.get(t.view.path,{language:f.code})}function W(f,r){if(!y.value){r==null||r();return}t.dialog.open({component:"k-text-dialog",props:{text:f},on:{submit:()=>{t.dialog.close(),r==null||r()}}})}return{__sfc:!0,props:s,panel:t,store:e,recursiveTranslateContent:p,label:d,confirm:y,syncableFields:v,translatableFields:c,translatableStructureFields:h,translatableBlocks:i,translateTitle:o,config:a,defaultLanguageData:u,defaultLanguage:m,currentContent:A,translatableContent:T,t:M,syncModelContent:H,translateModelContent:Q,updateModelDefaultLanguageData:x,getModelData:F,openModal:W}}});var J=function(){var p;var s=this,t=s._self._c,e=s._self._setupProxy;return e.config?t("k-section",{attrs:{label:e.label}},[e.panel.multilang?!e.config.translateFn&&!((p=e.config.DeepL)!=null&&p.apiKey)?t("k-box",{attrs:{theme:"empty"}},[t("k-text",[s._v(" You need to set the either a custom "),t("code",[s._v("translateFn")]),s._v(" or the "),t("code",[s._v("DeepL.apiKey")]),s._v(" option for the "),t("code",[s._v("johannschopplich.content-translator")]),s._v(" namespace in your Kirby configuration. ")])],1):e.translatableFields.length?e.config.allowDefaultLanguageOverwrite?t("k-box",{attrs:{theme:"none"}},[t("k-button-group",{attrs:{layout:"collapsed"}},[s._l(e.panel.languages.filter(d=>d.code!==e.panel.language.code),function(d){return t("k-button",{directives:[{name:"show",rawName:"v-show",value:e.syncableFields.length,expression:"syncableFields.length"}],key:d.code,attrs:{size:"sm",variant:"filled"},on:{click:function(y){e.openModal(e.panel.t("johannschopplich.content-translator.dialog.syncFrom",{language:d.name}),()=>e.syncModelContent(d))}}},[s._v(" "+s._s(e.panel.t("johannschopplich.content-translator.importFrom",{language:d.code.toUpperCase()}))+" ")])}),t("k-button",{attrs:{icon:"translate",size:"sm",variant:"filled",theme:"notice"},on:{click:function(d){e.openModal(e.panel.t("johannschopplich.content-translator.dialog.translate",{language:e.panel.language.name}),()=>e.translateModelContent(e.panel.language))}}},[s._v(" "+s._s(e.panel.t("johannschopplich.content-translator.translate",{language:e.panel.language.code.toUpperCase()}))+" ")])],2)],1):[t("k-box",{attrs:{theme:"none"}},[t("k-button-group",{attrs:{layout:"collapsed"}},[t("k-button",{directives:[{name:"show",rawName:"v-show",value:e.syncableFields.length,expression:"syncableFields.length"}],attrs:{disabled:e.panel.language.default,size:"sm",variant:"filled"},on:{click:function(d){e.openModal(e.panel.t("johannschopplich.content-translator.dialog.sync",{language:e.defaultLanguage.name}),()=>e.syncModelContent())}}},[s._v(" "+s._s(e.panel.t("johannschopplich.content-translator.sync"))+" ")]),t("k-button",{attrs:{disabled:e.panel.language.default,icon:"translate",size:"sm",variant:"filled",theme:"notice"},on:{click:function(d){e.openModal(e.panel.t("johannschopplich.content-translator.dialog.translate",{language:e.panel.language.name}),()=>e.translateModelContent(e.panel.language,e.defaultLanguage))}}},[s._v(" "+s._s(e.panel.t("johannschopplich.content-translator.translate",{language:e.panel.language.code.toUpperCase()}))+" ")])],1)],1),t("k-box",{directives:[{name:"show",rawName:"v-show",value:e.panel.language.default,expression:"panel.language.default"}],staticClass:"kct-mt-1",attrs:{theme:"none",text:e.panel.t("johannschopplich.content-translator.help.disallowDefaultLanguage")}})]:t("k-box",{attrs:{theme:"info"}},[t("k-text",[s._v(" You have to define at least one translatable field for the "),t("code",[s._v("translatableFields")]),s._v(" blueprint or in your Kirby configuration. ")])],1):t("k-box",{attrs:{theme:"info"}},[t("k-text",[s._v(" This section requires multi-language support to be enabled. ")])],1)],2):s._e()},X=[],Y=z(V,J,X,!1,null,null,null,null);const q=Y.exports;window.panel.plugin("johannschopplich/content-translator",{sections:{"content-translator":q}})})();
