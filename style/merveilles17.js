'use strict';


var splitH = Split(['#aside', '#main'], { sizes: [30, 70], gutterSize: 3, });
var aside = document.getElementById('aside');
var main = document.getElementById('main');

if (aside) {
  let els = aside.getElementsByTagName('details');
  for (let i = 0, max = els.length; i < max; i++) {
    let el = els[i];
    el.addEventListener("toggle", function(evt){
      if(el.open) {
        main.classList.add(el.id);
      } else {
        main.classList.remove(el.id);
      }
    }, false);
  }
  
  els = aside.getElementsByTagName('a');
  for (let i = 0, max = els.length; i < max; i++) {
    let el = els[i];
    el.addEventListener("click", function(event){
      let terms = main.querySelectorAll('.'+el.id);
      if (el.classList.contains('active')) {
        for (let z = 0, max = terms.length; z < max; z++) {
          terms[z].classList.remove('active');
        }
        el.classList.remove('active');
        event.preventDefault();
        return false;
      }
      else {
        el.classList.add('active');
        for (let z = 0, max = terms.length; z < max; z++) {
          terms[z].classList.add('active');
        }
      }
    });
  }
}

if(main) {
  let classes = ["persName", "tech", "name"];
  for (const cls of classes) {
    let matches = main.querySelectorAll("."+cls);
    for (let i = 0, max = matches.length; i < max; i++) {
      let el = matches[i];
      el.addEventListener("click", function(){
        let key = this.getAttribute("data-key");
        if (!key) key = cls+"nokey";
        let newHash = '#'+key;
        if (location.hash == newHash) return; // do no repeat
        location.hash = newHash;
      });
    }
  }
}


function getScrollParent(node) {
  if (node == null) return null;
  if (node.scrollTop) return node;
  return getScrollParent(node.parentNode);
}

window.onhashchange = function (e)
{
  let url = new URL(e.newURL);
  let hash = url.hash;
  return propaghi(hash);
}

window.onpopstate = function(event) {
  // before scroll
  // console.log("location: " + document.location + ", state: " + JSON.stringify(event.state));
};

function propaghi(hash)
{
  let id = decodeURIComponent(hash);
  if (id[0] == "#") id = id.substring(1);
  let el = document.getElementById(id);
  if (!el) return;
  const scrollable = getScrollParent(el);
  if (!scrollable) return;
  if (scrollable.lastScroll == scrollable.scrollTop) return;
  let newScroll = scrollable.scrollTop - 100;
  scrollable.scrollTop = newScroll;
  scrollable.lastScroll = newScroll;
}
if (window.location.hash) propaghi(window.location.hash);

function hitoks(form, style)
{
  let count = 0;
  let matches = document.querySelectorAll("a."+form);
}


