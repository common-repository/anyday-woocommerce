<?php
namespace Adm;

class Activator
{
    public function activate()
    {
    	add_option( 'activated_plugin', ADM_PLUGIN_SLUG );
    }
}