#Introduction

This is a very early prototype code for a GoodData REST API PHP library. Contributions are welcome.

#Usage

    <?php
    
        require 'GoodData.class.php';
        
        $gd = new GoodData();
        $gd->login('username','pass');
        $gd->getProjects();
    
    ?>