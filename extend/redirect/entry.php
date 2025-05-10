<?php
require '../../webapp_stdio.php';
class webapp_router_admin extends webapp_echo_masker
{

    function __construct(webapp $webapp)
    {
        parent::__construct($webapp, $webapp->admin(...));


        if ($this->auth)
        {
            $this->aside->append('button', 'Add Tagname')->setattr([
                //'onclick' => '$(this).action("?admin/aaa")'
            ]);

            $this->nav([
                ['Home', '?admin'],
                ['Tags', []]
            ]);
        }
    }
    function get_aaa()
    {
        $this->echo_json(['aaa' => 123]);
    }


    function form_tag(webapp_html $node = NULL):webapp_form
    {
        $form = new webapp_form($node);
        $form->field('tag', 'text');
        $form->button('Submit', 'submit');
        $form->xml['onsubmit'] = 'return $(this).action()';
        return $form;
    }

    function post_home()
    {
        $this->json1([1,2,3]);
    }
    function get_home()
    {
        $this->form_tag($this->main);
        $this->main->append('a', ['asdadwda',
            'href' => '?admin/home',
            'data-method' => 'post',
            'data-confirm' => 'asdadw',
            'onclick' => 'return $(this).action()'
        ]);
    }

}
new class extends webapp{};