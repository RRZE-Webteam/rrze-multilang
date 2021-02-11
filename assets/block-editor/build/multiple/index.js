!function(e){var t={};function n(r){if(t[r])return t[r].exports;var o=t[r]={i:r,l:!1,exports:{}};return e[r].call(o.exports,o,o.exports,n),o.l=!0,o.exports}n.m=e,n.c=t,n.d=function(e,t,r){n.o(e,t)||Object.defineProperty(e,t,{enumerable:!0,get:r})},n.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},n.t=function(e,t){if(1&t&&(e=n(e)),8&t)return e;if(4&t&&"object"==typeof e&&e&&e.__esModule)return e;var r=Object.create(null);if(n.r(r),Object.defineProperty(r,"default",{enumerable:!0,value:e}),2&t&&"string"!=typeof e)for(var o in e)n.d(r,o,function(t){return e[t]}.bind(null,o));return r},n.n=function(e){var t=e&&e.__esModule?function(){return e.default}:function(){return e};return n.d(t,"a",t),t},n.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},n.p="",n(n.s=14)}([function(e,t){e.exports=window.wp.element},function(e,t){e.exports=window.wp.components},function(e,t){e.exports=window.wp.i18n},function(e,t,n){var r=n(9),o=n(10),l=n(11),a=n(13);e.exports=function(e,t){return r(e)||o(e,t)||l(e,t)||a()}},function(e,t){e.exports=window.wp.data},function(e,t){e.exports=window.wp.compose},function(e,t){e.exports=window.wp.apiFetch},function(e,t){e.exports=window.wp.plugins},function(e,t){e.exports=window.wp.editPost},function(e,t){e.exports=function(e){if(Array.isArray(e))return e}},function(e,t){e.exports=function(e,t){if("undefined"!=typeof Symbol&&Symbol.iterator in Object(e)){var n=[],_n=!0,r=!1,o=void 0;try{for(var l,a=e[Symbol.iterator]();!(_n=(l=a.next()).done)&&(n.push(l.value),!t||n.length!==t);_n=!0);}catch(e){r=!0,o=e}finally{try{_n||null==a.return||a.return()}finally{if(r)throw o}}return n}}},function(e,t,n){var r=n(12);e.exports=function(e,t){if(e){if("string"==typeof e)return r(e,t);var n=Object.prototype.toString.call(e).slice(8,-1);return"Object"===n&&e.constructor&&(n=e.constructor.name),"Map"===n||"Set"===n?Array.from(e):"Arguments"===n||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)?r(e,t):void 0}}},function(e,t){e.exports=function(e,t){(null==t||t>e.length)&&(t=e.length);for(var n=0,r=new Array(t);n<t;n++)r[n]=e[n];return r}},function(e,t){e.exports=function(){throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}},function(e,t,n){"use strict";n.r(t);var r=n(7),o=n(3),l=n.n(o),a=n(0),i=n(8),c=n(1),u=n(5),s=n(4),f=n(2),p=n(6),b=n.n(p);Object(r.registerPlugin)("rrze-multilang-language-panel",{render:function(){var e=Object(s.useSelect)((function(e){return Object.assign({},e("core/editor").getCurrentPost(),rrzeMultilang.currentPost)}));if(-1==rrzeMultilang.localizablePostTypes.indexOf(e.type))return Object(a.createElement)(a.Fragment,null);if("auto-draft"==e.status)return Object(a.createElement)(a.Fragment,null);var t=Object(a.useState)(e.secondarySitesToLink),n=l()(t,2),r=n[0],o=(n[1],Object(a.useState)(e.secondarySitesToCopy)),p=l()(o,2),m=p[0];return p[1],Object(a.createElement)(i.PluginDocumentSettingPanel,{name:"rrze-multilang-language-panel",title:Object(f.__)("Language","rrze-multilang"),className:"rrze-multilang-language-panel"},Object(a.createElement)((function(){var t=[];return Object.entries(r).forEach((function(n){var r=l()(n,2),o=(r[0],r[1]),i=o.name+" — "+o.language,p=o.selected,m=Object(u.withState)({link:p})((function(t){var n=t.link,r=t.setState;return Object(a.createElement)(c.SelectControl,{label:i,value:n,options:o.options,onChange:function(t,n){r({link:t});var o=t.split(":"),l=o[0],a=o[1];b()({path:"/rrze-multilang/v1/link/"+e.id+"/blog/"+l+"/post/"+a,method:"POST"}).then((function(e){var t=e[a].blogName,n=e[a].postTitle;Object(s.dispatch)("core/notices").createInfoNotice(Object(f.__)("Linked to ".concat(n," on ").concat(t,"."),"rrze-multilang"),{isDismissible:!0,type:"snackbar",speak:!0})}))}})}));t.push(Object(a.createElement)(c.PanelRow,null,Object(a.createElement)(m,null)))})),Object(a.createElement)((function(e){return e.listItems.length?e.listItems:Object(a.createElement)("em",null,Object(f.__)("There are no websites available for translations.","rrze-multilang"))}),{listItems:t})}),null),Object(a.createElement)((function(){var t=0,n=function(e){return e.copying?Object(a.createElement)(c.Spinner,null):Object(a.createElement)(a.Fragment,null)},r=[];return Object.entries(m).forEach((function(o){var i=l()(o,2),p=(i[0],i[1]),m=Object(u.withState)({blogId:"0"})((function(e){var n=e.blogId,r=e.setState;return Object(a.createElement)(c.SelectControl,{label:Object(f.__)("Copy To:","rrze-multilang"),value:n,options:p.options,onChange:function(e){r({blogId:e}),t=e}})}));r.push(Object(a.createElement)(c.PanelRow,null,Object(a.createElement)(m,null))),r.push(Object(a.createElement)(c.PanelRow,null,Object(a.createElement)(c.Button,{isDefault:!0,onClick:function(){var n;n=t,b()({path:"/rrze-multilang/v1/copy/"+e.id+"/blog/"+n,method:"POST"}).then((function(e){var t=e[n].blogName;Object(s.dispatch)("core/notices").createInfoNotice(Object(f.__)("A copy has been added to ".concat(t,"."),"rrze-multilang"),{isDismissible:!0,type:"snackbar",speak:!0})}))}},Object(f.__)("Add Copy","rrze-multilang")),Object(a.createElement)(n,null)))})),Object(a.createElement)((function(e){return e.listItems.length?e.listItems:Object(a.createElement)(a.Fragment,null)}),{listItems:r})}),null))},icon:"translation"})}]);