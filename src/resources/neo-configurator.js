(()=>{"use strict";var t={n:e=>{var s=e&&e.__esModule?()=>e.default:()=>e;return t.d(s,{a:s}),s},d:(e,s)=>{for(var i in s)t.o(s,i)&&!t.o(e,i)&&Object.defineProperty(e,i,{enumerable:!0,get:s[i]})},o:(t,e)=>Object.prototype.hasOwnProperty.call(t,e)};const e=jQuery;var s=t.n(e);s().fn.insertAt=function(t,e){return this.each((function(){0===t?e.prepend(this):e.children().eq(t-1).after(this)}))};const i=Garnish;var n=t.n(i);const o=Craft;var a,l=t.n(o),r=new Uint8Array(16);function c(){if(!a&&!(a="undefined"!=typeof crypto&&crypto.getRandomValues&&crypto.getRandomValues.bind(crypto)||"undefined"!=typeof msCrypto&&"function"==typeof msCrypto.getRandomValues&&msCrypto.getRandomValues.bind(msCrypto)))throw new Error("crypto.getRandomValues() not supported. See https://github.com/uuidjs/uuid#getrandomvalues-not-supported");return a(r)}const h=/^(?:[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}|00000000-0000-0000-0000-000000000000)$/i;const d=function(t){return"string"==typeof t&&h.test(t)};for(var p=[],g=0;g<256;++g)p.push((g+256).toString(16).substr(1));const u=function(t){var e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:0,s=(p[t[e+0]]+p[t[e+1]]+p[t[e+2]]+p[t[e+3]]+"-"+p[t[e+4]]+p[t[e+5]]+"-"+p[t[e+6]]+p[t[e+7]]+"-"+p[t[e+8]]+p[t[e+9]]+"-"+p[t[e+10]]+p[t[e+11]]+p[t[e+12]]+p[t[e+13]]+p[t[e+14]]+p[t[e+15]]).toLowerCase();if(!d(s))throw TypeError("Stringified UUID is invalid");return s};const m=function(t,e,s){var i=(t=t||{}).random||(t.rng||c)();if(i[6]=15&i[6]|64,i[8]=63&i[8]|128,e){s=s||0;for(var n=0;n<16;++n)e[s+n]=i[n];return e}return u(i)},y={_stack:[[]],enter(t){let e=!(arguments.length>1&&void 0!==arguments[1])||arguments[1];if("string"==typeof t&&(t=this.fromFieldName(t)),e){const e=this.getNamespace();e.push(...t),t=e}this._stack.push(t)},enterByFieldName(t){let e=!(arguments.length>1&&void 0!==arguments[1])||arguments[1];this.enter(this.fromFieldName(t),e)},leave(){return this._stack.length>1?this._stack.pop():this.getNamespace()},getNamespace(){return Array.from(this._stack[this._stack.length-1])},parse(t){return"string"==typeof t?t.indexOf("[")>-1?this.fromFieldName(t):t.indexOf("-")>-1?t.split("-"):t.indexOf(".")>-1?t.split("."):t:Array.from(t)},value(t){let e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:"-";const s=this.getNamespace();return s.push(t),s.join(e)},fieldName(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:"";const e=this.toFieldName();return e?e+t.replace(/([^'"[\]]+)([^'"]*)/,"[$1]$2"):t},toString(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:"-";return this.getNamespace().join(t)},toFieldName(){const t=this.getNamespace();switch(t.length){case 0:return"";case 1:return t[0]}return t[0]+"["+t.slice(1).join("][")+"]"},fromFieldName:t=>t.match(/[^[\]\s]+/g)||[]},f={settings:null},k=n().Base.extend({_selected:!1,init(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};t=Object.assign({},f,t),this._settings=t.settings},getSettings(){return this._settings},select(){this.toggleSelect(!0)},deselect(){this.toggleSelect(!1)},toggleSelect:function(t){this._selected="boolean"==typeof t?t:!this._selected,this.trigger("toggleSelect",{selected:this._selected})},isSelected(){return this._selected}}),_={namespace:[],html:"",layout:null,id:-1,blockId:null,blockName:""},B=n().Base.extend({_templateNs:[],init(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};t=Object.assign({},_,t),this._templateNs=y.parse(t.namespace),this._id=0|t.id,this._blockTypeId=t.blockTypeId,this.$container=s()(t.html).find(".layoutdesigner"),this.$container.removeAttr("id");const e=this.$container.find('input[name="fieldLayout"]');e.length>0&&(e[0].name="neoBlockType".concat(this._blockTypeId,"[fieldLayout]"),t.layout&&(e[0].value=JSON.stringify(t.layout))),y.enter(this._templateNs),this._fld=new(l().FieldLayoutDesigner)(this.$container,{customizableTabs:!0,customizableUi:!0}),y.leave();const i=()=>{const t="[data-type=benf-neo-fieldlayoutelements-ChildBlocksUiElement]",e=this._fld.$uiLibraryElements.filter(t),i=this._fld.$tabContainer.find(t);e.toggleClass("hidden",i.length>0||s()("body.dragging .draghelper"+t).length>0),i.hasClass("velocity-animating")&&i.removeClass("hidden")};i(),this._tabObserver=new window.MutationObserver(i),this._tabObserver.observe(this._fld.$tabContainer[0],{childList:!0,subtree:!0})},getId(){return this._id},getBlockTypeId(){return this._blockTypeId},getConfig(){const t={tabs:[]};for(const e of this._fld.config.tabs){const s=[];for(const t of e.elements){const e={};for(const s in t)e[s]="required"!==s||t[s]?t[s]:"";s.push(e)}t.tabs.push({elements:s,name:e.name.slice()})}return t}}),b={namespace:[],fieldLayout:null},w=k.extend({_templateNs:[],init(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};this.base(t);const e=this.getSettings();t=Object.assign({},b,t),this._templateNs=y.parse(t.namespace),this._fieldLayout=t.fieldLayout,this.$container=this._generateBlockType(e);const s=this.$container.find("[data-neo-bt]");this.$nameText=s.filter('[data-neo-bt="text.name"]'),this.$handleText=s.filter('[data-neo-bt="text.handle"]'),this.$moveButton=s.filter('[data-neo-bt="button.move"]'),this.$actionsButton=s.filter('[data-neo-bt="button.actions"]'),this.$actionsMenu=s.filter('[data-neo-bt="container.menu"]'),this._actionsMenu=new(n().MenuBtn)(this.$actionsButton),this._actionsMenu.on("optionSelect",(t=>this["@actionSelect"](t))),this.addListener(this.$actionsButton,"click",(t=>t.stopPropagation())),e&&(e.on("change",(()=>this._updateTemplate())),e.on("destroy",(()=>this.trigger("destroy"))),this._updateTemplate()),this.deselect()},_generateBlockType(t){const e=t.getErrors(),i=(Array.isArray(e)?e:Object.keys(e)).length>0;return s()('\n      <div class="nc_sidebar_list_item'.concat(i?" has-errors":"",'">\n        <div class="label" data-neo-bt="text.name">').concat(t.getName(),'</div>\n        <div class="smalltext light code" data-neo-bt="text.handle">').concat(t.getHandle(),'</div>\n        <a class="move icon" title="').concat(l().t("neo","Reorder"),'" role="button" data-neo-bt="button.move"></a>\n        <button class="settings icon menubtn" title="').concat(l().t("neo","Actions"),'" role="button" type="button" data-neo-bt="button.actions"></button>\n        <div class="menu" data-neo-bt="container.menu">\n          <ul class="padded">\n            <li><a data-icon="field" data-action="copy">').concat(l().t("neo","Copy"),'</a></li>\n            <li class="disabled"><a data-icon="brush" data-action="paste">').concat(l().t("neo","Paste"),'</a></li>\n            <li><a data-icon="share" data-action="clone">').concat(l().t("neo","Clone"),'</a></li>\n            <li><a class="error" data-icon="remove" data-action="delete">').concat(l().t("neo","Delete"),"</a></li>\n          </ul>\n        </div>\n      </div>"))},getFieldLayout(){return this._fieldLayout},loadFieldLayout(){if(this._fieldLayout)return Promise.resolve();this.trigger("beforeLoadFieldLayout");const t=this.getSettings(),e=t.getFieldLayoutConfig(),s=t.getFieldLayoutId(),i=e?{layout:e}:{layoutId:s};return new Promise(((e,n)=>{l().sendActionRequest("POST","neo/configurator/render-field-layout",{data:i}).then((i=>{this._fieldLayout=new B({namespace:[...this._templateNs,this._id],html:i.data.html,id:s,blockTypeId:t.getId()}),this.trigger("afterLoadFieldLayout"),e()})).catch(n)}))},toggleSelect:function(t){this.base(t);const e=this.getSettings(),s=this.getFieldLayout(),i=this.isSelected();e&&e.$container.toggleClass("hidden",!i),s?s.$container.toggleClass("hidden",!i):i&&this.loadFieldLayout(),this.$container.toggleClass("is-selected",i)},_updateTemplate(){const t=this.getSettings();t&&(this.$nameText.text(t.getName()),this.$handleText.text(t.getHandle()),this.$container.toggleClass("is-child",!t.getTopLevel()))},"@actionSelect"(t){const e=s()(t.option);if(!e.hasClass("disabled"))switch(e.attr("data-action")){case"copy":this.trigger("copy");break;case"paste":this.trigger("paste");break;case"clone":this.trigger("clone");break;case"delete":window.confirm(l().t("neo","Are you sure you want to delete this block type?"))&&this.getSettings().destroy()}}}),v=n().Base.extend({$container:new(s()),_sortOrder:0,getSortOrder(){return this._sortOrder},setSortOrder(t){const e=this._sortOrder;this._sortOrder=0|t,e!==this._sortOrder&&this.trigger("change",{property:"sortOrder",oldValue:e,newValue:this._sortOrder})},getFocusElement:()=>new(s()),destroy(){var t;null===(t=this.$foot)||void 0===t||t.remove(),this.trigger("destroy")},_refreshSetting(t,e,s){(s=!n().prefersReducedMotion()&&("boolean"!=typeof s||s))?e?t.hasClass("hidden")&&t.removeClass("hidden").css({opacity:0,marginBottom:-t.outerHeight()}).velocity({opacity:1,marginBottom:24},"fast"):t.hasClass("hidden")||t.css({opacity:1,marginBottom:24}).velocity({opacity:0,marginBottom:-t.outerHeight()},"fast",(()=>{t.addClass("hidden")})):t.toggleClass("hidden",!e).css("margin-bottom",e?24:"")}}),$={namespace:[],id:null,sortOrder:0,fieldLayoutId:null,fieldLayoutConfig:null,name:"",handle:"",description:"",maxBlocks:0,maxSiblingBlocks:0,maxChildBlocks:0,topLevel:!0,childBlocks:null,childBlockTypes:[],html:"",js:"",errors:{}},S=v.extend({_templateNs:[],_childBlockTypes:[],_initialised:!1,$sortOrderInput:new(s()),$nameInput:new(s()),$handleInput:new(s()),$descriptionInput:new(s()),$maxBlocksInput:new(s()),$maxSiblingBlocksInput:new(s()),$maxChildBlocksInput:new(s()),init(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};t=Object.assign({},$,t),this._templateNs=y.parse(t.namespace),this._childBlockTypes=[],this._childBlocks=t.childBlocks,this._id=t.id,this._fieldLayoutId=t.fieldLayoutId,this._fieldLayoutConfig=t.fieldLayoutConfig,this._errors=t.errors,this._js=t.js,this._settingsChildBlockTypes=t.childBlockTypes,this.$container=s()(t.html);const e=this.$container.find("[data-neo-bts]");this.$sortOrderInput=e.filter('[data-neo-bts="input.sortOrder"]'),this.$nameInput=e.filter('[data-neo-bts="input.name"]'),this.$handleInput=e.filter('[data-neo-bts="input.handle"]'),this.$descriptionInput=e.filter('[data-neo-bts="input.description"]'),this.$maxBlocksInput=e.filter('[data-neo-bts="input.maxBlocks"]'),this.$maxSiblingBlocksInput=e.filter('[data-neo-bts="input.maxSiblingBlocks"]'),this.$maxChildBlocksInput=e.filter('[data-neo-bts="input.maxChildBlocks"]'),this.$maxChildBlocksContainer=e.filter('[data-neo-bts="container.maxChildBlocks"]'),this.$topLevelInput=e.filter('[data-neo-bts="input.topLevel"]'),this.$topLevelContainer=e.filter('[data-neo-bts="container.topLevel"]'),this.$childBlocksInput=e.filter('[data-neo-bts="input.childBlocks"]'),this.$childBlocksContainer=e.filter('[data-neo-bts="container.childBlocks"]'),this.$deleteButton=e.filter('[data-neo-bts="button.delete"]'),this.setSortOrder(t.sortOrder),this.setName(t.name),this.setHandle(t.handle),this.setDescription(t.description),this.setMaxBlocks(t.maxBlocks),this.setMaxSiblingBlocks(t.maxSiblingBlocks),this.setMaxChildBlocks(t.maxChildBlocks),this.setTopLevel(t.topLevel)},initUi(){if(!this._initialised){this.$foot=s()(this._js),n().$bod.append(this.$foot),l().initUiElements(this.$container),this._childBlocksSelect=this.$childBlocksInput.data("checkboxSelect"),this._topLevelLightswitch=this.$topLevelInput.data("lightswitch"),this._handleGenerator=new(l().HandleGenerator)(this.$nameInput,this.$handleInput),""!==this.getHandle()&&this._handleGenerator.stopListening();for(const t of this._settingsChildBlockTypes)this.addChildBlockType(t);this.setChildBlocks(this._childBlocks),this.addListener(this.$nameInput,"keyup change",(()=>{this.setName(this.$nameInput.val()),this._handleGenerator.listening&&setTimeout((()=>this.setHandle(this.$handleInput.val())),200)})),this.addListener(this.$handleInput,"keyup change textchange",(()=>this.setHandle(this.$handleInput.val()))),this.addListener(this.$descriptionInput,"keyup change textchange",(()=>this.setDescription(this.$descriptionInput.val()))),this.addListener(this.$maxBlocksInput,"keyup change",(()=>this.setMaxBlocks(this.$maxBlocksInput.val()))),this.addListener(this.$maxSiblingBlocksInput,"keyup change",(()=>this.setMaxSiblingBlocks(this.$maxSiblingBlocksInput.val()))),this.addListener(this.$maxChildBlocksInput,"keyup change",(()=>this.setMaxChildBlocks(this.$maxChildBlocksInput.val()))),this.addListener(this._topLevelLightswitch,"change",(()=>this.setTopLevel(this._topLevelLightswitch.on))),this.addListener(this.$deleteButton,"click",(()=>{window.confirm(l().t("neo","Are you sure you want to delete this block type?"))&&this.destroy()})),this.$childBlocksInput.on("change","input",(()=>this._refreshMaxChildBlocks())),this._initialised=!0}},_generateChildBlocksCheckbox(t){y.enter(this._templateNs);const e=y.value("childBlock-"+t.getId(),"-"),i=y.fieldName("childBlocks");return y.leave(),s()('\n      <div>\n        <input type="checkbox" value="'.concat(t.getHandle(),'" id="').concat(e,'" class="checkbox" name="').concat(i,'[]" data-neo-btsc="input">\n        <label for="').concat(e,'" data-neo-btsc="text.label">').concat(t.getName(),"</label>\n      </div>"))},getFocusInput(){return this.$nameInput},getId(){return this._id},getFieldLayoutId(){return this._fieldLayoutId},getFieldLayoutConfig(){return Object.assign({},this._fieldLayoutConfig)},isNew(){return/^new/.test(this.getId())},getErrors(){return this._errors},setSortOrder(t){this.base(t),this.$sortOrderInput.val(this.getSortOrder())},getName(){return this._name},setName(t){if(t!==this._name){const e=this._name;this._name=t,this.$nameInput.val()!==this._name&&this.$nameInput.val(this._name),this.trigger("change",{property:"name",oldValue:e,newValue:this._name})}},getHandle(){return this._handle},setHandle(t){if(t!==this._handle){const e=this._handle;this._handle=t,this.$handleInput.val()!==this._handle&&this.$handleInput.val(this._handle),this.trigger("change",{property:"handle",oldValue:e,newValue:this._handle})}},getDescription(){return this._description},setDescription(t){if(t!==this._description){const e=this._description;this._description=t,this.$descriptionInput.val()!==this._description&&this.$descriptionInput.val(this._description),this.trigger("change",{property:"description",oldValue:e,newValue:this._description})}},getMaxBlocks(){return this._maxBlocks},setMaxBlocks(t){const e=this._maxBlocks,s=Math.max(0,0|t);0===s&&this.$maxBlocksInput.val(null),e!==s&&(this._maxBlocks=s,this._maxBlocks>0&&parseInt(this.$maxBlocksInput.val())!==this._maxBlocks&&this.$maxBlocksInput.val(this._maxBlocks),this.trigger("change",{property:"maxBlocks",oldValue:e,newValue:this._maxBlocks}))},getMaxSiblingBlocks(){return this._maxSiblingBlocks},setMaxSiblingBlocks(t){const e=this._maxSiblingBlocks,s=Math.max(0,0|t);0===s&&this.$maxSiblingBlocksInput.val(null),e!==s&&(this._maxSiblingBlocks=s,this._maxSiblingBlocks>0&&parseInt(this.$maxSiblingBlocksInput.val())!==this._maxSiblingBlocks&&this.$maxSiblingBlocksInput.val(this._maxSiblingBlocks),this.trigger("change",{property:"maxSiblingBlocks",oldValue:e,newValue:this._maxSiblingBlocks}))},getMaxChildBlocks(){return this._maxChildBlocks},setMaxChildBlocks(t){const e=this._maxChildBlocks,s=Math.max(0,0|t);0===s&&this.$maxChildBlocksInput.val(null),e!==s&&(this._maxChildBlocks=s,this._maxChildBlocks>0&&parseInt(this.$maxChildBlocksInput.val())!==this._maxChildBlocks&&this.$maxChildBlocksInput.val(this._maxChildBlocks),this.trigger("change",{property:"maxChildBlocks",oldValue:e,newValue:this._maxChildBlocks}))},getTopLevel(){return this._topLevel},setTopLevel(t){const e=this._topLevel,s=!!t;e!==s&&(this._topLevel=s,this._topLevelLightswitch&&this._topLevelLightswitch.on!==this._topLevel&&(this._topLevelLightswitch.on=this._topLevel,this._topLevelLightswitch.toggle()),this.trigger("change",{property:"topLevel",oldValue:e,newValue:this._topLevel}))},getChildBlocks(){const t=this._childBlocksSelect,e=[];var i;return void 0===t?!0===this._childBlocks||Array.from(null!==(i=this._childBlocks)&&void 0!==i?i:[]):!!t.$all.prop("checked")||(t.$options.each((function(t){const i=s()(this);i.prop("checked")&&e.push(i.val())})),e.length>0&&e)},setChildBlocks(t){const e=this._childBlocksSelect;if(!0===t||"*"===t)e.$all.prop("checked",!0),e.onAllChange();else if(Array.isArray(t)){e.$all.prop("checked",!1);for(const s of t)e.$options.filter('[value="'.concat(s,'"]')).prop("checked",!0)}else e.$all.prop("checked",!1),e.$options.prop("checked",!1);this._refreshMaxChildBlocks(!1)},addChildBlockType(t){if(!this._childBlockTypes.includes(t)){const e=t.getSettings(),s=this._generateChildBlocksCheckbox(e);this._childBlockTypes.push(t),this.$childBlocksContainer.append(s),this._refreshChildBlocks();const i=this._childBlocksSelect,n=i.$all.prop("checked");i.$options=i.$options.add(s.find("input")),n&&i.onAllChange();const o=".childBlock"+this.getId();e.on("change"+o,(e=>this["@onChildBlockTypeChange"](e,t,s))),e.on("destroy"+o,(e=>this.removeChildBlockType(t)))}},removeChildBlockType(t){const e=this._childBlockTypes.indexOf(t);if(e>=0){this._childBlockTypes.splice(e,1);const s=t.getSettings(),i=this.$childBlocksContainer.children().eq(e);i.remove();const n=this._childBlocksSelect;n.$options=n.$options.remove(i.find("input"));const o=".childBlock"+this.getId();s.off(o),this._refreshChildBlocks()}},_refreshChildBlocks(){const t=Array.from(this._childBlockTypes),e=this.$childBlocksContainer.children(),s=s=>e.get(t.indexOf(s));this._childBlockTypes=this._childBlockTypes.sort(((t,e)=>t.getSettings().getSortOrder()-e.getSettings().getSortOrder())),e.remove();for(const t of this._childBlockTypes){const e=s(t);this.$childBlocksContainer.append(e)}},_refreshMaxChildBlocks(t){this._refreshSetting(this.$maxChildBlocksContainer,!!this.getChildBlocks(),t)},"@onChildBlockTypeChange"(t,e,s){const i=s.find("[data-neo-btsc]"),n=i.filter('[data-neo-btsc="input"]'),o=i.filter('[data-neo-btsc="text.label"]');switch(t.property){case"name":o.text(t.newValue);break;case"handle":n.val(t.newValue);break;case"sortOrder":this._refreshChildBlocks()}}},{_totalNewBlockTypes:0,getNewId(){return"new".concat(this._totalNewBlockTypes++)}}),C={namespace:[]},x=k.extend({_templateNs:[],init(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};this.base(t),t=Object.assign({},C,t);const e=this.getSettings();this._templateNs=y.parse(t.namespace),this.$container=this._generateGroup(e);const s=this.$container.find("[data-neo-g]");this.$nameText=s.filter('[data-neo-g="text.name"]'),this.$moveButton=s.filter('[data-neo-g="button.move"]'),e&&(e.on("change",(()=>this._updateTemplate())),e.on("destroy",(()=>this.trigger("destroy")))),this.deselect()},_generateGroup(t){var e;return s()('\n      <div class="nc_sidebar_list_item type-heading">\n        <div class="label" data-neo-g="text.name">'.concat(null!==(e=t.getName())&&void 0!==e?e:"",'</div>\n        <a class="move icon" title="').concat(l().t("neo","Reorder"),'" role="button" data-neo-g="button.move"></a>\n      </div>'))},toggleSelect:function(t){this.base(t);const e=this.getSettings(),s=this.isSelected();e&&e.$container.toggleClass("hidden",!s),this.$container.toggleClass("is-selected",s)},_updateTemplate(){const t=this.getSettings();t&&this.$nameText.text(t.getName())}}),L={namespace:[],id:null,sortOrder:0,name:"",alwaysShowDropdown:null,defaultAlwaysShowGroupDropdowns:!0},I=v.extend({_templateNs:[],$sortOrderInput:new(s()),$nameInput:new(s()),$handleInput:new(s()),$maxBlocksInput:new(s()),init(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};t=Object.assign({},L,t),this._templateNs=y.parse(t.namespace),this._id=t.id,this._alwaysShowDropdown=t.alwaysShowDropdown,this._defaultAlwaysShowGroupDropdowns=t.defaultAlwaysShowGroupDropdowns,this.$container=this._generateGroupSettings();const e=this.$container.find("[data-neo-gs]");this.$sortOrderInput=e.filter('[data-neo-gs="input.sortOrder"]'),this.$nameInput=e.filter('[data-neo-gs="input.name"]'),this.$deleteButton=e.filter('[data-neo-gs="button.delete"]'),this.$alwaysShowDropdownContainer=e.filter('[data-neo-gs="container.alwaysShowDropdown"]'),this.setSortOrder(t.sortOrder),this.setName(t.name),this.addListener(this.$nameInput,"keyup change",(()=>this.setName(this.$nameInput.val()))),this.addListener(this.$deleteButton,"click",(()=>{window.confirm(l().t("neo","Are you sure you want to delete this group?"))&&this.destroy()}))},_generateGroupSettings(){y.enter(this._templateNs);const t=y.fieldName("sortOrder"),e=y.value("name","-"),i=y.fieldName("name"),n=y.value("alwaysShowDropdown","-"),o=y.fieldName("alwaysShowDropdown");y.leave();const a=[{value:"show",label:l().t("neo","Show")},{value:"hide",label:l().t("neo","Hide")},{value:"global",label:this._defaultAlwaysShowGroupDropdowns?l().t("neo","Use global setting (Show)"):l().t("neo","Use global setting (Hide)")}],r=l().ui.createTextField({type:"text",id:e,name:i,label:l().t("neo","Name"),instructions:l().t("neo","This can be left blank if you just want an unlabeled separator."),value:this.getName()});return r.find("input").attr("data-neo-gs","input.name"),s()('\n      <div>\n      <input type="hidden" name="'.concat(t,'" value="').concat(this.getSortOrder(),'" data-neo-gs="input.sortOrder">\n      <div>\n        ').concat(s()('<div class="field">').append(r).html(),'\n        <div data-neo-gs="container.alwaysShowDropdown">\n          <div class="field">\n            ').concat(l().ui.createSelectField({label:l().t("neo","Always Show Dropdown?"),instructions:l().t("neo","Whether to show the dropdown for this group if it only has one available block type."),id:n,name:o,options:a,value:this._alwaysShowDropdown?"show":!1===this._alwaysShowDropdown?"hide":"global"}).html(),'\n          </div>\n        </div>\n      </div>\n      <hr>\n      <a class="error delete" data-neo-gs="button.delete">').concat(l().t("neo","Delete group"),"</a>\n    </div>"))},getFocusInput(){return this.$nameInput},getId(){return this._id},setSortOrder(t){this.base(t),this.$sortOrderInput.val(this.getSortOrder())},getName(){return this._name},setName(t){if(t!==this._name){const e=this._name;this._name=t,this.$nameInput.val(this._name),this._refreshAlwaysShowDropdown(),this.trigger("change",{property:"name",oldValue:e,newValue:this._name})}},getAlwaysShowDropdown(){return this._alwaysShowDropdown},_refreshAlwaysShowDropdown(t){this._refreshSetting(this.$alwaysShowDropdownContainer,!!this._name,t)}},{_totalNewGroups:0,getNewId(){return"new".concat(this._totalNewGroups++)}}),T={namespace:[],blockTypes:[],groups:[],blockTypeSettingsHtml:"",blockTypeSettingsJs:"",fieldLayoutHtml:""},N=n().Base.extend({_templateNs:[],_items:[],init(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};t=Object.assign({},T,t);const e=l().formatInputId(t.namespace),i=s()("#".concat(e,"-neo-configurator"));this.$container=i.children(".field").children(".input"),this._templateNs=y.parse(t.namespace),this._blockTypeSettingsHtml=t.blockTypeSettingsHtml,this._blockTypeSettingsJs=t.blockTypeSettingsJs,this._fieldLayoutHtml=t.fieldLayoutHtml,this._items=[];const o=this.$container.find("[data-neo]");this.$mainContainer=o.filter('[data-neo="container.main"]'),this.$sidebarContainer=o.filter('[data-neo="container.sidebar"]'),this.$blockTypesContainer=o.filter('[data-neo="container.blockTypes"]'),this.$settingsContainer=o.filter('[data-neo="container.settings"]'),this.$fieldLayoutContainer=o.filter('[data-neo="container.fieldLayout"]'),this.$blockTypeButton=o.filter('[data-neo="button.blockType"]'),this.$groupButton=o.filter('[data-neo="button.group"]'),this.$settingsButton=o.filter('[data-neo="button.settings"]'),this.$fieldLayoutButton=o.filter('[data-neo="button.fieldLayout"]'),this._itemSort=new(n().DragSort)(null,{container:this.$blockTypeItemsContainer,handle:'[data-neo-bt="button.move"], [data-neo-g="button.move"]',axis:"y",onSortChange:()=>this._updateItemOrder()});const a=[],r=[...this._templateNs,"blockTypes"],c=[...this._templateNs,"groups"];for(const e of t.blockTypes){const t=new S({namespace:[...r,e.id],sortOrder:e.sortOrder,id:e.id,name:e.name,handle:e.handle,description:e.description,maxBlocks:e.maxBlocks,maxSiblingBlocks:e.maxSiblingBlocks,maxChildBlocks:e.maxChildBlocks,topLevel:e.topLevel,html:e.settingsHtml,js:e.settingsJs,errors:e.errors,fieldLayoutId:e.fieldLayoutId,fieldLayoutConfig:e.fieldLayoutConfig,childBlockTypes:a.filter((t=>t instanceof w))}),i=new w({namespace:r,settings:t});i.on("copy.configurator",(()=>this._copyBlockType(i))),i.on("paste.configurator",(()=>this._pasteBlockType())),i.on("clone.configurator",(()=>this._createBlockTypeFrom(i))),i.on("beforeLoadFieldLayout.configurator",(()=>this.$fieldLayoutContainer.append(s()('<span class="spinner"/></span>')))),i.on("afterLoadFieldLayout.configurator",(()=>{this.$fieldLayoutContainer.children(".spinner").remove(),this._addFieldLayout(i.getFieldLayout())})),a.push(i)}for(const e of t.groups){const s=new I({namespace:[...c,e.id],sortOrder:e.sortOrder,id:e.id,name:e.name,alwaysShowDropdown:e.alwaysShowDropdown,defaultAlwaysShowGroupDropdowns:t.defaultAlwaysShowGroupDropdowns}),i=new x({namespace:c,settings:s});a.push(i)}for(const t of a.sort(((t,e)=>t.getSettings().getSortOrder()-e.getSettings().getSortOrder())))this.addItem(t);for(const e of this.getBlockTypes()){const s=e.getSettings(),i=t.blockTypes.find((t=>t.handle===s.getHandle()));s.setChildBlocks(i.childBlocks)}const h=()=>{const t=!window.localStorage.getItem("neo:copyBlockType");for(const e of this.getBlockTypes())e.$actionsMenu.find('[data-action="paste"]').parent().toggleClass("disabled",t)};h(),this.addListener(document,"visibilitychange.configurator",h),this.selectTab("settings"),this.addListener(this.$blockTypeButton,"click","@newBlockType"),this.addListener(this.$groupButton,"click","@newGroup"),this.addListener(this.$settingsButton,"click",(()=>this.selectTab("settings"))),this.addListener(this.$fieldLayoutButton,"click",(()=>this.selectTab("fieldLayout")))},addItem(t){let e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:-1;const s=t.getSettings();if(this._insertAt(t.$container,e),this._itemSort.addItems(t.$container),s&&(this.$settingsContainer.append(s.$container),t instanceof w&&s.initUi()),this.$mainContainer.removeClass("hidden"),this.addListener(t.$container,"click","@selectItem"),t.on("destroy.configurator",(()=>this.removeItem(t,!1))),t instanceof w&&this._addFieldLayout(t.getFieldLayout()),this._items.push(t),this._updateItemOrder(),t instanceof w)for(const e of this.getBlockTypes()){const s=e.getSettings();s&&s.addChildBlockType(t)}this.trigger("addItem",{item:t,index:e})},_addFieldLayout(t){t&&this.$fieldLayoutContainer.append(t.$container)},removeItem(t,e){if(e="boolean"==typeof e&&e){const e=l().t("neo","Are you sure you want to delete this {type}?",{type:t instanceof w?"block type":t instanceof x?"group":"item"});window.confirm(e)&&this.removeItem(t,!1)}else{const e=t.getSettings();if(this._itemSort.removeItems(t.$container),t.$container.remove(),e&&e.$container.remove(),t instanceof w){const e=t.getFieldLayout();e&&e.$container.remove()}this.removeListener(t.$container,"click"),t.off(".configurator"),this._updateItemOrder(),0===this._items.length&&this.$mainContainer.addClass("hidden"),this.trigger("removeItem",{item:t})}},getItems(){return Array.from(this._items)},getItemByElement(t){return this._items.find((e=>e.$container.is(t)))},getSelectedItem(){return this._items.find((t=>t.isSelected()))},selectItem(t,e){e="boolean"!=typeof e||e;const s=t?t.getSettings():null;for(const e of this._items){const s=e===t;if(e.toggleSelect(s),s){const t=!(e instanceof w);this.$fieldLayoutButton.toggleClass("hidden",t),t&&this.selectTab("settings")}}e&&s&&!n().isMobileBrowser()&&setTimeout((()=>s.getFocusInput().focus()),100)},getBlockTypes(){return this._items.filter((t=>t instanceof w))},getGroups(){return this._items.filter((t=>t instanceof x))},selectTab(t){this.$settingsContainer.toggleClass("hidden","settings"!==t),this.$fieldLayoutContainer.toggleClass("hidden","fieldLayout"!==t),this.$settingsButton.toggleClass("is-selected","settings"===t),this.$fieldLayoutButton.toggleClass("is-selected","fieldLayout"===t)},_getNewBlockTypeSettingsHtml(t,e){return this._blockTypeSettingsHtml.replace(/__NEOBLOCKTYPE_ID__/g,t).replace(/__NEOBLOCKTYPE_SORTORDER__/,e)},_getNewBlockTypeSettingsJs(t){return this._blockTypeSettingsJs.replace(/__NEOBLOCKTYPE_ID__/g,t)},_getNewFieldLayoutHtml(){return this._fieldLayoutHtml.replace(/&quot;uid&quot;:&quot;([a-f0-9-]+)&quot;/,"&quot;uid&quot;:&quot;".concat(m(),"&quot;"))},_updateItemOrder(){const t=[];this._itemSort.$items.each(((e,s)=>{const i=this.getItemByElement(s);if(i){const s=i.getSettings();s&&s.setSortOrder(e+1),t.push(i)}})),this._items=t},_createBlockTypeFrom(t){const e=[...this._templateNs,"blockTypes"],i=S.getNewId(),n=this.getSelectedItem(),o=n?n.getSettings().getSortOrder():-1;if(null===t){const t=new S({childBlockTypes:this.getBlockTypes(),id:i,namespace:[...e,i],sortOrder:this._items.length,html:this._getNewBlockTypeSettingsHtml(i,o),js:this._getNewBlockTypeSettingsJs(i)}),s=new B({blockTypeId:i,html:this._getNewFieldLayoutHtml(),namespace:[...e,i]});this._initBlockType(e,t,s,o)}else{const n=t.getSettings(),a=new S({childBlocks:n.getChildBlocks(),childBlockTypes:this.getBlockTypes(),handle:"".concat(n.getHandle(),"_").concat(Date.now()),id:i,maxBlocks:n.getMaxBlocks(),maxChildBlocks:n.getMaxChildBlocks(),maxSiblingBlocks:n.getMaxSiblingBlocks(),name:n.getName(),description:n.getDescription(),namespace:[...e,i],sortOrder:this._items.length,topLevel:n.getTopLevel(),html:this._getNewBlockTypeSettingsHtml(i,o),js:this._getNewBlockTypeSettingsJs(i)}),r=s()('<div class="nc_sidebar_list_item type-spinner"><span class="spinner"></span></div>');this._insertAt(r,o),t.loadFieldLayout().then((()=>{const s=t.getFieldLayout().getConfig();if(s.tabs.length>0){const t={layout:s};l().queue.push((()=>new Promise(((s,n)=>{l().sendActionRequest("POST","neo/configurator/render-field-layout",{data:t}).then((t=>{const n=new B({blockTypeId:i,html:t.data.html,namespace:[...e,i]});this.$blockTypesContainer.find(".type-spinner").remove(),this._initBlockType(e,a,n,o),s()})).catch(n)}))))}else{const t=new B({blockTypeId:i,html:this._getNewFieldLayoutHtml(),namespace:[...e,i]});this.$blockTypesContainer.find(".type-spinner").remove(),this._initBlockType(e,a,t,o)}})).catch((()=>l().cp.displayError(l().t("neo","Couldn’t create new block type."))))}},_initBlockType(t,e,s,i){const n=new w({namespace:t,settings:e,fieldLayout:s});this.addItem(n,i),this.selectItem(n),this.selectTab("settings"),n.on("copy.configurator",(()=>this._copyBlockType(n))),n.on("paste.configurator",(()=>this._pasteBlockType())),n.on("clone.configurator",(()=>this._createBlockTypeFrom(n)))},_copyBlockType(t){t.loadFieldLayout().then((()=>{const e=t.getSettings(),s={childBlocks:e.getChildBlocks(),handle:e.getHandle(),layout:t.getFieldLayout().getConfig(),maxBlocks:e.getMaxBlocks(),maxChildBlocks:e.getMaxChildBlocks(),maxSiblingBlocks:e.getMaxSiblingBlocks(),name:e.getName(),topLevel:e.getTopLevel()};window.localStorage.setItem("neo:copyBlockType",JSON.stringify(s)),this.getBlockTypes().forEach((t=>t.$actionsMenu.find('[data-action="paste"]').parent().removeClass("disabled")))})).catch((()=>l().cp.displayError(l().t("neo","Couldn’t copy block type."))))},_pasteBlockType(){const t=window.localStorage.getItem("neo:copyBlockType");if(!t)return;const e=JSON.parse(t),s=this.getBlockTypes().map((t=>t.getSettings().getHandle())),i=Array.isArray(e.childBlocks)?e.childBlocks.filter((t=>s.includes(t))):!!e.childBlocks||[],n=new S({childBlocks:i,childBlockTypes:this.getBlockTypes(),handle:e.handle,maxBlocks:e.maxBlocks,maxChildBlocks:e.maxChildBlocks,maxSiblingBlocks:e.maxSiblingBlocks,name:e.name,topLevel:e.topLevel}),o=new B({html:this._getNewFieldLayoutHtml(),layout:e.layout}),a=new w({settings:n,fieldLayout:o});this._createBlockTypeFrom(a)},_insertAt(t,e){const i=s()(t);e>=0&&e<this._items.length?i.insertAt(e,this.$blockTypesContainer):this.$blockTypesContainer.append(i)},"@newBlockType"(){this._createBlockTypeFrom(null)},"@newGroup"(){const t=[...this._templateNs,"groups"],e=I.getNewId(),s=new I({namespace:[...t,e],sortOrder:this._items.length,id:e}),i=new x({namespace:t,settings:s}),n=this.getSelectedItem(),o=n?n.getSettings().getSortOrder():-1;this.addItem(i,o),this.selectItem(i)},"@selectItem"(t){const e=this.getItemByElement(t.currentTarget);this.selectItem(e)}});var O;const F=null!==(O=window)&&void 0!==O?O:void 0,D=[];F.Neo={Configurator:N,configurators:D,createConfigurator(){const t=new N(arguments.length>0&&void 0!==arguments[0]?arguments[0]:{});return D.push(t),t}}})();
//# sourceMappingURL=neo-configurator.js.map