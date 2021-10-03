<?php

namespace bjc\roundcubeimap;

class message {
    
    protected $rcube_imap_generic;
    protected $rcube_message_header;
       
    public function __construct(\rcube_imap_generic $rcube_imap_generic, \rcube_message_header $rcube_message_header) {
        $this->rcube_imap_generic = $rcube_imap_generic;
        $this->rcube_message_header = $rcube_message_header;
        
    }

    public function getUID() {
        $returnvalue = $this->rcube_message_header->uid;
        
        return $returnvalue;
    }
    
    public function getDate() {
        
        $date = $this->rcube_message_header->get("date");
        
        $datetime_object = \rcube_utils::anytodatetime($date);
        
        return $datetime_object;
        
    }
    
    public function getHeader($field) {
        
        $returnvalue = $this->rcube_message_header->get($field);

        if (is_array($returnvalue)) {
            $returnvalue = (object) $returnvalue;
        } 
        
        return $returnvalue;
        
    }
    
    public function getHeaders() {
        
        
        
    }
        
}