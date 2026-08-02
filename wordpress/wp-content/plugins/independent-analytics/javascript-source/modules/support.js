const $ = jQuery;

const Support = {
    setup() {
        if ($('body').hasClass('analytics_page_independent-analytics-support-center')) {

            const form = document.getElementById('search-form')
            const searchField = document.getElementById('search-field');

            form.onsubmit = function(e) {
                e.preventDefault();
                if (searchField.value == '') {
                    window.open('https://independentwp.com/knowledgebase/');
                } else {
                    window.open('https://independentwp.com/?post_type=kb_article&s=' + searchField.value);
                }
            }
        }
    }
}

export { Support };