(()=>{"use strict";var e={n:t=>{var r=t&&t.__esModule?()=>t.default:()=>t;return e.d(r,{a:r}),r},d:(t,r)=>{for(var n in r)e.o(r,n)&&!e.o(t,n)&&Object.defineProperty(t,n,{enumerable:!0,get:r[n]})},o:(e,t)=>Object.prototype.hasOwnProperty.call(e,t)};const t=window.React,r=window.wp.element,n=window.wp.apiFetch;var c=e.n(n);const o=window.wp.i18n;var a,s;document.getElementById("vipps-mobilepay-recurring-checkout")&&(a=document.querySelector("#vipps-mobilepay-recurring-checkout"),s=(0,t.createElement)((function(){const{pendingOrderId:e,data:n}=window.VippsRecurringCheckout,[a,s]=(0,r.useState)(!!n.session),[i,u]=(0,r.useState)(n.session),[l,d]=(0,r.useState)(null),p=(0,r.useRef)(null);(0,r.useEffect)((()=>{a||(m(),s(!0))}),[i,a]),(0,r.useEffect)((()=>{n.redirect_url&&(window.location.href=n.redirect_url)}),[n]);const m=(0,r.useCallback)((()=>{c()({path:"/vipps-mobilepay-recurring/v1/checkout/session",method:"POST"}).then((e=>u(e)))}),[e]),w=(0,r.useCallback)((()=>{c()({path:"/vipps-mobilepay-recurring/v1/checkout/session",method:"GET"}).then((e=>d(e)))}),[]);(0,r.useEffect)((()=>{if(!i.token)return;const e=setInterval(w,1e4);return()=>{clearInterval(e)}}),[i]),(0,r.useEffect)((()=>{l&&l.redirect_url&&(window.location.href=l.redirect_url)}),[l]);const f=(0,r.useCallback)((e=>{const t=p.current.getAttribute("src"),r=new URL(t).origin;e.origin===r&&(console.log(e.data),"resize"===e.data.type&&(p.current.style.height=`${e.data.frameHeight}px`),"payment_url"===e.data.type&&(window.location.href=e.data.paymentUrl))}),[p.current]);return(0,r.useEffect)((()=>{if(p.current)return window.addEventListener("message",f),()=>{window.removeEventListener("message",f)}}),[p.current,i.token]),(0,t.createElement)("form",{id:"vippsdata",className:"woocommerce-checkout"},(0,t.createElement)("div",{className:"vipps-recurring-checkout-page"},(0,t.createElement)("div",{className:"vipps-recurring-checkout-page__loading"},"EXPIRED"!==l?.status&&(0,t.createElement)(t.Fragment,null,!i.token&&(0,t.createElement)("div",{className:"vipps-recurring-checkout-page__loading__spinner"}),i.token&&(0,t.createElement)("iframe",{ref:p,src:`${i.checkoutFrontendUrl||i.src}?token=${i.token}`,frameborder:"0",width:"100%"})),"EXPIRED"===l?.status&&(0,t.createElement)("div",null,(0,o.__)("Checkout session expired. Please refresh to start a new session.","vipps-recurring-payments-gateway-for-woocommerce")))))}),null),r.createRoot?(0,r.createRoot)(a).render(s):(0,r.render)(s,a))})();