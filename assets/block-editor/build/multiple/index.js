!function(e){var t={};function n(r){if(t[r])return t[r].exports;var o=t[r]={i:r,l:!1,exports:{}};return e[r].call(o.exports,o,o.exports,n),o.l=!0,o.exports}n.m=e,n.c=t,n.d=function(e,t,r){n.o(e,t)||Object.defineProperty(e,t,{enumerable:!0,get:r})},n.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},n.t=function(e,t){if(1&t&&(e=n(e)),8&t)return e;if(4&t&&"object"==typeof e&&e&&e.__esModule)return e;var r=Object.create(null);if(n.r(r),Object.defineProperty(r,"default",{enumerable:!0,value:e}),2&t&&"string"!=typeof e)for(var o in e)n.d(r,o,function(t){return e[t]}.bind(null,o));return r},n.n=function(e){var t=e&&e.__esModule?function(){return e.default}:function(){return e};return n.d(t,"a",t),t},n.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},n.p="",n(n.s=14)}([function(e,t){e.exports=window.wp.element},function(e,t){e.exports=window.wp.components},function(e,t){e.exports=window.wp.i18n},function(e,t,n){var r=n(9),o=n(10),a=n(11),l=n(13);e.exports=function(e,t){return r(e)||o(e,t)||a(e,t)||l()}},function(e,t){e.exports=window.wp.data},function(e,t){e.exports=window.wp.compose},function(e,t){e.exports=window.wp.apiFetch},function(e,t){e.exports=window.wp.plugins},function(e,t){e.exports=window.wp.editPost},function(e,t){e.exports=function(e){if(Array.isArray(e))return e}},function(e,t){e.exports=function(e,t){if("undefined"!=typeof Symbol&&Symbol.iterator in Object(e)){var n=[],_n=!0,r=!1,o=void 0;try{for(var a,l=e[Symbol.iterator]();!(_n=(a=l.next()).done)&&(n.push(a.value),!t||n.length!==t);_n=!0);}catch(e){r=!0,o=e}finally{try{_n||null==l.return||l.return()}finally{if(r)throw o}}return n}}},function(e,t,n){var r=n(12);e.exports=function(e,t){if(e){if("string"==typeof e)return r(e,t);var n=Object.prototype.toString.call(e).slice(8,-1);return"Object"===n&&e.constructor&&(n=e.constructor.name),"Map"===n||"Set"===n?Array.from(e):"Arguments"===n||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)?r(e,t):void 0}}},function(e,t){e.exports=function(e,t){(null==t||t>e.length)&&(t=e.length);for(var n=0,r=new Array(t);n<t;n++)r[n]=e[n];return r}},function(e,t){e.exports=function(){throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}},function(e,t,n){"use strict";n.r(t);var r=n(7),o=n(3),a=n.n(o),l=n(0),i=n(8),c=n(1),u=n(5),s=n(4),f=n(2),b=n(6),p=n.n(b);Object(r.registerPlugin)("rrze-multilang-language-panel",{render:function(){var e=Object(s.useSelect)((function(e){return Object.assign({},e("core/editor").getCurrentPost(),rrzeMultilang.currentPost)}));if(-1==rrzeMultilang.localizablePostTypes.indexOf(e.type))return Object(l.createElement)(l.Fragment,null);if("auto-draft"==e.status)return Object(l.createElement)(l.Fragment,null);var t=Object(l.useState)(e.secondarySitesToLink),n=a()(t,2),r=n[0],o=(n[1],Object(l.useState)(e.secondarySitesToCopy)),b=a()(o,2),m=b[0],g=b[1];return Object(l.createElement)(i.PluginDocumentSettingPanel,{name:"rrze-multilang-language-panel",title:Object(f.__)("Language","rrze-multilang"),className:"rrze-multilang-language-panel"},Object(l.createElement)((function(){var t=[];return Object.entries(r).forEach((function(n){var r=a()(n,2),o=(r[0],r[1]),i=o.name+" — "+o.language,b=o.selected,m=Object(u.withState)({link:b})((function(t){var n=t.link,r=t.setState;return Object(l.createElement)(c.SelectControl,{label:i,value:n,options:o.options,onChange:function(t,n){r({link:t});var o=t.split(":"),a=o[0],l=o[1];p()({path:"/rrze-multilang/v1/link/"+e.id+"/blog/"+a+"/post/"+l,method:"POST"}).then((function(e){var t=e[l].blogName,n=e[l].postTitle;Object(s.dispatch)("core/notices").createInfoNotice(Object(f.__)("Linked to ".concat(n," on ").concat(t,"."),"rrze-multilang"),{isDismissible:!0,type:"snackbar",speak:!0})}))}})}));t.push(Object(l.createElement)(c.PanelRow,null,Object(l.createElement)(m,null)))})),Object(l.createElement)((function(e){return e.listItems.length?e.listItems:Object(l.createElement)("em",null,Object(f.__)("There are no websites available for translations.","rrze-multilang"))}),{listItems:t})}),null),Object(l.createElement)((function(){var t=[],n=0;return Object.entries(m).forEach((function(r){var o=a()(r,2),i=o[0],b=o[1],d=Object(u.withState)({blogId:i})((function(e){var t=e.blogId,r=e.setState;return Object(l.createElement)(c.SelectControl,{label:Object(f.__)("Copy To:","rrze-multilang"),value:t,options:b.options,onChange:function(e){r({blogId:e}),n=e}})}));null==b.creating?(t.push(Object(l.createElement)(c.PanelRow,null,Object(l.createElement)(d,null))),t.push(Object(l.createElement)(c.Button,{isDefault:!0,onClick:function(){var t,r;t=n,(r=Object.assign({},m))[t]={creating:!0},g(r),p()({path:"/rrze-multilang/v1/copy/"+e.id+"/blog/"+t,method:"POST"}).then((function(e){var n=Object.assign({},m);n[t]={blogId:e[t].blogId,blogName:e[t].blogName,creating:!1},g(n);var r=n[t].blogName;Object(s.dispatch)("core/notices").createInfoNotice(Object(f.__)("A copy has been added to ".concat(r,"."),"rrze-multilang"),{isDismissible:!0,type:"snackbar",speak:!0})}))}},Object(f.__)("Add Copy","rrze-multilang")))):b.creating&&t.push(Object(l.createElement)(c.Spinner,null))})),Object(l.createElement)((function(e){return e.listItems.length?e.listItems:Object(l.createElement)(l.Fragment,null)}),{listItems:t})}),null))},icon:"translation"})}]);