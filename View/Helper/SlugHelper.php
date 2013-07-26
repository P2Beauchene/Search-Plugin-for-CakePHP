<?php

App::uses('AppHelper', 'View/Helper');
App::import('Search.Lib', 'Accents');

class SlugHelper extends AppHelper {
    public function slug($string, $separator = '-') {
        return Accents::slug($string, $separator);
    }
}
?>
