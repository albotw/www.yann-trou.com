Vue.component("projet-component", {
    template: `
            <div>
                <h3>{{ nom }}</h3>
                <a href="url">{{ url }}</a>
                <a href="url">{{ downloadlink }}</a>
                <button v-for="tag in tags">
                    {{ tag.tagname }}
                </button>
            </div> `,
    props: ["nom", "url", "downloadlink", "tags"],
});

let csrf;
let vue1;
let vue2;
window.addEventListener("DOMContentLoaded", function()
{
    csrf = $("#csrf").text();

    setupTags();
    setupPortfolio();

    $("#searchbar").on("keyup", function(){
        let text = $("#searchbar").val();
        search(text);
    });
});

async function search(text)
{
    try{
        var data = await $.post("router.php", {csrf_token: csrf, action: "searchProjets", text: text});
        vue1.json = JSON.parse(data);
    }catch(error)
    {
        console.error(error);
    }
}

async function setupTags()
{
    try
    {
        var data = await $.post("router.php", {csrf_token: csrf, action: "getAllTags"});
        var json = JSON.parse(data);
        vue2 = new Vue({el: "#taglist", data: {json}});
    }catch(error)
    {
        console.error(error);
    }
}

async function setupPortfolio()
{
    try
    {
        var data = await $.post("router.php", {csrf_token: csrf, action: "getAllProjets"});
        var json = JSON.parse(data);
        vue1 = new Vue({el: "#projets", data: {json}});
    }catch(error)
    {
        console.error(error);
    }
}