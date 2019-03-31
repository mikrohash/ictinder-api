<?php

function incl_rel_once($rel, $context) {
    $abs = dirname($context).DIRECTORY_SEPARATOR.$rel;
    include_once($abs);
}