import{S as p,r,o as m,c as x,w as o,a as e,u as _,b as n,d as f,e as k,F as V}from"./app.40bb12c6.js";const v=n("\xA0"),C=n("\xA0"),P=n("\xA0"),L=n("\xA0"),N=n("\xA0"),I=n("\xA0"),B=n("\xA0"),D=f("h2",null,"Vehicle Diary Login",-1),F=n(" Login "),U=n("Reset Password"),S={__name:"login",setup(h){const t=p(),i=()=>{t.checkLogin(t.userName,t.userPassword)},b=(u,l)=>{u.props.suffixIcon=u.props.suffixIcon==="eye"?"eyeClosed":"eye",u.props.type=u.props.type==="password"?"text":"password"};return(u,l)=>{const a=r("b-row"),s=r("b-col"),d=r("FormKit"),w=r("b-link"),y=r("b-form-row"),g=r("b-container");return m(),x(g,null,{default:o(()=>[e(a,null,{default:o(()=>[v]),_:1}),e(a,null,{default:o(()=>[C]),_:1}),e(a,null,{default:o(()=>[P]),_:1}),e(a,null,{default:o(()=>[L]),_:1}),e(a,null,{default:o(()=>[N]),_:1}),e(a,null,{default:o(()=>[I]),_:1}),e(a,null,{default:o(()=>[B]),_:1}),e(y,null,{default:o(()=>[e(s),e(s),e(s),e(s),e(s),e(s),e(s),e(s,{cols:"4",class:"loginLayout"},{default:o(()=>[e(d,{type:"group"},{default:o(()=>[D,e(d,{type:"text",label:"Username",value:"JohnL",help:"Please enter your Dunster House username.",modelValue:_(t).userName,"onUpdate:modelValue":l[0]||(l[0]=c=>_(t).userName=c)},null,8,["modelValue"]),e(d,{type:"password",name:"password",value:"super-secret",label:"Password",help:"Login password",validation:"required","validation-visibility":"live",modelValue:_(t).userPassword,"onUpdate:modelValue":l[1]||(l[1]=c=>_(t).userPassword=c),"suffix-icon":"eyeClosed",onSuffixIconClick:b},null,8,["modelValue"]),e(d,{type:"button",onClick:i,style:{"background-color":"#0275ff","padding-left":"25px","margin-left":"0px"}},{default:o(()=>[F]),_:1}),e(w,{href:"",onClick:l[2]||(l[2]=c=>_(t).resetPassword())},{default:o(()=>[U]),_:1})]),_:1})]),_:1}),e(s)]),_:1})]),_:1})}}},$=f("h1",null,"Updated Despatch Diary",-1),q={__name:"home",setup(h){return p(),(t,i)=>(m(),k(V,null,[$,e(S)],64))}};export{q as default};