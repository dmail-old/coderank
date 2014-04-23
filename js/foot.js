// les boutons contenant un lien récupère la classe focus quand le lien a le focus
function extendLink(button){
	var a = button.getElement('a');
	if( !a ) return;
	
	/*
	button.store('link', a);
	button.addEvent('click', function(){
		var a = this.retrieve('link');
		document.location.href = a.getAttribute('href');
	});
	*/
	
	a.store('button', button);
	a.addEvents({
		focus:function(){
			this.retrieve('button').addClass('focus');
		},
		blur: function(){
			this.retrieve('button').removeClass('focus');
		}
	});
}

// modifie tous les %s d'une chaine par les arguments reçus
function sprintf(str){
	for(var i=1,j=arguments.length;i<j;i++) str = str.replace('%s',arguments[i]);
	return str;
}

// ne montre que le groupe du select ayant le label groupLabel
function showGroup(select, groupLabel){
	function copyOptions(options, to){
		var i = 0, j = options.length, option;
		for(;i<j;i++){
			option = options[i];
			to.appendChild(option);	
			// on passe les options de l'optgroup actif dans le select
			//array.push(to.appendChild(new Option(option.text, option.value, false, option.selected)));
		}
		return options;
	}
	
	// on enlève les options n'appartenant pas au select de base
	var options = select.retrieve('options');
	if( options ){
		copyOptions(options, select.retrieve('optgroup'));
		select.erase('options');
	}
	// on remet les optgroups dans le select
	var optgroups = select.retrieve('optgroups');
	if( optgroups ){
		select.adopt(optgroups);
		select.erase('optgroups');
	}
	// si aucun language de choisit plus rien à faire
	if( groupLabel == 'all' || groupLabel === true ) return;
	
	optgroups = [];
	var childs = select.childNodes, i = childs.length, optgroup;
	while(i--){
		optgroup = childs[i];
		if( optgroup.nodeName.toLowerCase() != 'optgroup' ) continue;
		
		optgroup.dispose(); // supprime l'optgroup du DOM (performance et bug d'option qui reste pas selected)
		optgroups.push(optgroup);
		
		options = optgroup.getChildren('option');
		var j = options.length, option;
		
		if( optgroup.getAttribute('label') == groupLabel ){
			select.store('optgroup', optgroup);
			select.store('options', copyOptions(options, select));
		}
		else{
			while(j--){
				option = options[j];
				if( option.selected ){
					option.selected = false;
					select.fireEvent('change'); // n'est pas fired sinon
				}
			}
		}
	}
	select.store('optgroups', optgroups);
};

// fixe le pied de page en bas
function fixFoot(){
	var all = $('all');
	if( !all ){	
		var all = new Element('div', {id:'all', style:"left:-9000px;position:absolute;width:100%;height:100%;"});
		document.body.insertBefore(all, document.body.firstChild);
	}
	
	var page = $('page'), header = $('header'), footer = $('footer');
	// on tient compte du padding de page pour que le footer soit en bonne position
	var padding = page.getStyle('paddingTop').toInt() + page.getStyle('paddingBottom').toInt();
	page.style.minHeight = (all.offsetHeight - (header.offsetHeight + padding + footer.offsetHeight)) + 'px';
}

// corrige le comportement des blocks expandables
function fixExpand(){
	var elements = $$('.expand'), i = elements.length, element, label, input;
	
	while(i--){
		element = elements[i];
		label = element.getElement('label');
		input = element.getElement('input');
		
		label.addEvent('mousedown', function(e){
			e.preventDefault(); // corrige le fait que le mécanisme de sélection est génant
		});
		// pour chrome et safari qui ont un bug sur :checked+
		input.addEvent('change', function(){
			this.getNext('.content').toggleClass('show', this.checked);
		});
	}	
}

// PLUS UTILISE: affiche en temps réel le nb de caractères restants d'un champ de saisie
function observeLength(input, len){   
	function truncate(){
		this.value.substring(0, this.retrieve('len'));
	}
	
	function update(){
		var diff = this.retrieve('len') - this.value.length;
		this.retrieve('div').set('text', sprintf('%s caractères restants', diff));
	}
  
	function keypress(e){
		if( e.key == 'backspace' ) return;
		
		// on vérifie lé sélection pour qu'on puise écrire par dessus une sélection courante
		var len = this.retrieve('len'), value = this.value, selected = Math.abs(this.selectionStart - this.selectionEnd);
		selected = isNaN(selected) ? 0 : selected;
		
		update.call(this);
		
		if( value.length == len && selected < 1 ){
			e.preventDefault();
		}
		else if( value.length > len && selected < (value.length-len) ){
			e.preventDefault();
			truncate.call(this);
		}
	};
  
	function change(e){		
		update.call(this);
		truncate.call(this);
	};
	
	if( !len ) return;
	
	var div = new Element('div', {'class':'maxlength'});
	
	input.store('len', len);
	input.store('div', div);
	input.parentNode.appendChild(div);
	input.addEvents({keypress: keypress, change: change});
	input.fireEvent('change');
};

// ajoute un message en haut de la page
function showMessage(type, text){
	var p = new Element('p', {'class':type, 'text': text});
	p.addEvent('click', hideMessage);
	$('page').insertBefore(p, $('nav').nextSibling);
}

function hideMessage(e){
	if( e.target.nodeName.toLowerCase() != 'a' ) this.dispose();
}

/* Modifie l'objet Request de mootools pour que la réponse ne soit un succès que si elle commence par 'ok'  */
var Ajax = {
	json: function(data){
		var json = Function.attempt(function(){ return JSON.decode(data); });
		return json != null ? json : null;
	},
	ok: function(text){ return text.substr(0,2) === 'ok'; },
	clean: function(text){ return text.substr(2); }
};

Request.implement({
	options:{
		isSuccess:function(){
			if( !this.isSuccess() ) return false;
			var text = this.response.text;
			if( !Ajax.ok(text) ) return false;
			this.response.text = Ajax.clean(text);
			return true;
		}
	},
	
	complete: function(){
		this.fireEvent('complete');
	}
});

// inhibe les liens pour supprimer afin que leur action se fasse en AJAX
function deletewithAjax(e){
	e.preventDefault();
	if( !confirm(lang.remove_confirm) ) return;
	
	var self = this, url = this.href.split('?'), req = new Request({url:url[0], method:'get'});
	
	req.addEvents({
		request: function(){
			console.log('début requete');
		},
		complete: function(){
			console.log('fin requete');
		},
		success: function(text){
			console.log('réponse', text);	
			text = JSON.decode(text);			
			showMessage(text.type, text.text);
			
			// supprime l'élément html
			if( text.type == 'success' ){
				var parent = self.getParent('tr,li');
				
				if( parent ){
					var morph = new Fx.Morph(parent);
					morph.addEvent('complete', function(){
						var head = parent.getPrevious('[role="heading"]');
						// si y'as un compteur on diminue le nombre d'item de la liste
						if( head ){
							var span = head.getElement('.count');
							if( span ) span.set('text', parseInt(span.get('text'))-1);
						}
						parent.dispose();
					});
					morph.start({opacity:0, height:0});
				}
			}
		},
		failure: function(req){
			showMessage('error', lang.ajax_fail);
			console.log('échec', req);
		}
	});
	
	req.send(url[1]);
}

// dès que la page est prète on lance toutes ces fonctions
window.addEvent('domready', function(){
	$$('.button').each(extendLink);
	
	fixFoot();
	window.addEvent('resize', fixFoot);
	
	fixExpand();
	
	Array.each($('page').childNodes, function(child){
		if( typeOf(child) != 'element' ) return;
		if( child.hasClass('info') || child.hasClass('success') || child.hasClass('warning') || child.hasClass('error') ){
			child.addEvent('click', hideMessage);
		}
	});
	
	$$('*[data-maxlength]').each(function(input){
		observeLength(input, input.getAttribute('data-maxlength'));
	});
	
	$$('a.remove').addEvent('click', deletewithAjax);
});