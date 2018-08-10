<?php
/******************************************************************************
*
* Purpose: Inizializzazione dei parametri per la creazione della mappa

* Author:  Filippo Formentini formentini@perspectiva.it
*
******************************************************************************
*
* Copyright (c) 2017 Perspectiva di Formentini Filippo
*
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version. See the COPYING file.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with p.mapper; if not, write to the Free Software
* Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*
******************************************************************************/

class Lexer
{
    private $_patterns = array();
    private $_length = 0;
    private $_tokens = array();
    private $_delimeter = '';
    private $_last_error = '';
    private $_throttle_limit = 1000; //limite di elaborazione per evitare loop infiniti
	private $_throttle = 0; 
	
    public function __construct($delimeter = "#")
    {
        $this->_delimeter = $delimeter;
    }
	
	public function resetTokens()
    {
        $this->_tokens = array();
    }
	
    /** 
    * Add a regular expression to the Lexer
    *
    * @param string $name name of the token
    * @param string $pattern the regular expression to match
    */
    public function add($name, $pattern)
    {
        $this->_patterns[$this->_length]['name'] = $name;
        $this->_patterns[$this->_length]['regex'] = $pattern;
        $this->_length++;
    }
    /** 
    * Tokenizes a reference to an input string, 
    * removing matches from the beginning of the string
     * 
    * @param string &$input the input string to tokenize
     * 
     *@return boolean|string returns the matched token on success, boolean false on failure
    */
    public function tokenize(&$input)
    {
        for($i = 0; $i < $this->_length; $i++)
        {
            if(@preg_match($this->_patterns[$i]['regex'], $input, $matches))
            {
                $this->_tokens[] = array('name' => $this->_patterns[$i]['name'],
                                         'token' => $matches[0]);
                
                //remove last found token from the $input string
                //we use preg_quote to escape any regular expression characters in the matched input
                $input = trim(preg_replace($this->_delimeter."^".preg_quote($matches[0], $this->_delimeter).$this->_delimeter, "", $input));
                //correzione del bug per lo 0
				if($matches[0] === '0' || $matches[0] === 0){
					return True;
				}
				return $matches[0];
            }
            elseif(preg_match($this->_patterns[$i]['regex'], $input, $matches) === false)
            {
                    $this->_last_error = 'Error occured at $_patterns['.$i.']';
                    return false;
            }
        }
        return false;
    }
	
    public function __get($item)
    {
        switch($item){
            case 'tokens':
                return $this->_tokens;
            case 'last_error':
                return $this->_last_error;
        }
    }
}

class Parser{
	public $lexer = NULL;
	
	//esegue il parsing della prima espressione tra parentesi tonde trovata
	public function parseBracket($tokens){
		//print_r("_____________________________________________________");
		//print_r($tokens);
		
		$startIndex = -1;
		$endIndex = -1;
		for($i = 0; $i < count($tokens); $i++)
        {
			if($tokens[$i]['name'] == "CLOSE BRACKET"){
				$endIndex = $i;
				for($k = $i; $k >= 0; $k--)
				{
					if($tokens[$k]['name'] == "OPEN BRACKET"){
						$startIndex = $k;
						break;
					}
				}
				break;
			}
		}
		if($startIndex > -1 && $endIndex > -1){
			//valuto l'espressione
			//$sTokens  = array_slice($tokens, $startIndex, ($endIndex- $startIndex + 1));
			//valori senza parentesi
			$tokensToChange = array_slice($tokens, $startIndex + 1, ($endIndex - $startIndex - 1));
			array_splice($tokens, $startIndex, ($endIndex - $startIndex + 1),
						  $this->evaluateExpression($tokensToChange)
						);
		}
		return $tokens;
	}
	
	//valuta tutte le funzioni all'interno di tokens senza le parentesi
	public function evaluateFunctions($tokens){
		//espressione compare
		while($this->hasToken($tokens,["FUNCTION"])){
			$result = 0;
			$index = 0;
			$found = False;
			for($fi=0; $fi < count($tokens); $fi++){
				if($tokens[$fi]['name'] == "FUNCTION"){
					switch($tokens[$fi]['token']){
						case "length":
							$result = strlen(trim($tokens[$fi + 2]['token'], "'"));
							$found = True;
							$index = $fi;
							array_splice($tokens, ($index), 4, array(array("name" => "NUMBER","token" => $result)));
						break;
						default:
								break;
					}
				}
			}
		}
		return $tokens;
	}
	
	//valuta tutte le espressioni all'interno di tokens senza le parentesi
	public function evaluateOperators($tokens){
		//espressione compare
		while($this->hasToken($tokens,["OPERATOR"])){
			$result = 0;
			$index = 0;
			$found = False;
			for($oi=0; $oi < count($tokens); $oi++){
				if($tokens[$oi]['name'] == "OPERATOR"){
					switch($tokens[$oi]['token']){
						case "+":
							//verifico se sono solo stringhe
							if($tokens[$oi-1]['name'] == "STRING" && $tokens[$oi+1]['name'] == "STRING"){
								$result = (trim($tokens[$oi-1]['token'], "'"))."".(trim($tokens[$oi+1]['token'], "'"));
								$found = True;
								$index = $oi - 1;
								array_splice($tokens, ($index), 3, array(array("name" => "STRING","token" => $result)));
							}
						break;
						default:
								break;
					}
				}
			}
		}
		return $tokens;
	}
	
	
	//valuta tutte le espressioni all'interno di tokens senza le parentesi
	public function evaluateExpression($tokens){
		//espressione compare
		while($this->hasToken($tokens,["COMPARE"])){
			$result = 0;
			$index = 0;
			$found = False;
			for($i=0; $i < count($tokens); $i++){
				if($tokens[$i]['name'] == "COMPARE"){
					switch($tokens[$i]['token']){
						case "=":
						case "==":
							$result = ($tokens[$i-1]['token'] == $tokens[$i+1]['token']) ? 1 : 0;
							$found = True;
							$index = $i;
						break;
						case "!=":
						case "<>":
							$result = ($tokens[$i-1]['token'] != $tokens[$i+1]['token']) ? 1 : 0;
							$found = True;
							$index = $i;
						break;
						case ">":
							$result = ($tokens[$i-1]['token'] > $tokens[$i+1]['token']) ? 1 : 0;
							$found = True;
							$index = $i;
						break;
						case ">=":
							$result = ($tokens[$i-1]['token'] >= $tokens[$i+1]['token']) ? 1 : 0;
							$found = True;
							$index = $i;
						break;
						case "<":
							$result = ($tokens[$i-1]['token'] < $tokens[$i+1]['token']) ? 1 : 0;
							$found = True;
							$index = $i;
						break;
						case "<=":
							$result = ($tokens[$i-1]['token'] <= $tokens[$i+1]['token']) ? 1 : 0;
							$found = True;
							$index = $i;
						break;
						case "IN":
						case "in":
							$result = (in_array(trim($tokens[$i-1]['token'], "'"), explode(",", trim($tokens[$i+1]['token'], "'")))) ? 1 : 0;
							$found = True;
							$index = $i;
						break;
						case "~":
						case "LIKE":
						case "like":
							$result = (strpos($tokens[$i-1]['token'], $tokens[$i+1]['token'])) ? 1 : 0;
							$found = True;
							$index = $i;
						break;
						default:
								break;
					}
				}
			}
			if($found){
				//sostituisco l'espressione con un booleano
				array_splice($tokens, ($index-1), (3), array(array("name" => "BOOLEAN","token" => $result)));
			}
		}
		//espressione boolean
		while($this->hasToken($tokens,["LOGIC"])){
			$result = 0;
			$index = 0;
			$found = False;
			$lenReplace = 0;
			for($i=0; $i < count($tokens); $i++){
				if($tokens[$i]['name'] == "LOGIC"){
					switch($tokens[$i]['token']){
						case "AND":
						case "and":
							if(boolval($tokens[$i-1]['token']) && boolval($tokens[$i+1]['token'])){
								$result = 1;
							}
							$found = True;
							//dimensione di replace
							$lenReplace = 3;
							$index = $i - 1;
							break;
						case "OR":
						case "or":
							if(boolval($tokens[$i-1]['token']) || boolval($tokens[$i+1]['token'])){
								$result = 1;
							}
							$found = True;
							//dimensione di replace
							$lenReplace = 3;
							$index = $i - 1;
							break;
						case "NOT":
						case "not":
							if(!boolval($tokens[$i+1]['token'])){
								$result = 1;
							}
							$found = True;
							//dimensione di replace
							$lenReplace = 2;
							$index = $i;
							break;
						 default:
							break;
					}
				}
			}
			if($found){
				//non utilizzato per i boolean
				array_splice($tokens, ($index), ($lenReplace), array(array("name" => "BOOLEAN","token" => $result)));
				
				
			}
		}
		return $tokens;
	}
	
	
	
	
	
	/*if(count($sTokens) == 3){
			switch($sTokens[1]['name']){
				case "BOOLEAN":
					$result = $sTokens[1]['token'];
					$found = 1;
				break;
			}
		}*/
	
	public function hasToken($tokens, $tokensToFind ){
		//controllo del limite
		$this->_throttle++;
		if($this->_throttle > $this->_throttle_limit){
			throw new Exception("Espressione non valida");
		}
		foreach($tokens as $token){
			//print_r($token);
			if(in_array($token["name"], $tokensToFind)){
				return True;
			}
		}
		return False;
	}
	
	public function printTokens($tokens){
		$result = "";
		foreach($tokens as $token){
			$result .=  $token["token"];
		}
		return $result;
	}
	
	public function createLexer(){
		$lexer = new Lexer();
		$strings = <<<'SCRIPT'
/^("|')(\\?.)*?\1/     
SCRIPT
;
$comment = <<<'SCRIPT'
/^\/\*.*\*\//     
SCRIPT
;
		$lexer->add("FUNCTION", "/^length/");
		$lexer->add("DOUBLE", "/^[0-9]+\.[0-9]+/");
		$lexer->add("NUMBER", "/^[0-9]+/");
		$lexer->add("STRING", $strings);
		$lexer->add("VARIABLE", "/^%[a-zA-Z]+/");
		//$tokenizer->add("EQUALS", "/^=/");
		$lexer->add("COMPARE", "/^(>=|<=|<>|==|!=|=|>|<|IN|in|~|like|LIKE)/");
		$lexer->add("LOGIC", "/^(AND|OR|NOT|and|or|not)/");
		$lexer->add("OPERATOR", "/^(\+|\-)/");
		$lexer->add("OPEN BLOCK", "/^{/");
		$lexer->add("CLOSE BLOCK", "/^}/");
		$lexer->add("OPEN BRACKET", "/^\(/");
		$lexer->add("CLOSE BRACKET", "/^\)/");
		$lexer->add("CONTROL-FLOW STATEMENT", "/^(IF|ELSE|WHERE|FOR)/");
		$lexer->add("COMMENT", $comment);
		$lexer->add("NAME", "/^[a-zA-Z]+/");
		return $lexer;
	}
	
	
	/*
	* Valuta una espressione come un boolean
	*/
	public function evaluateString($expression){
		//elimino gli escape
		$expression = str_replace("\\", "", $expression);
		
		if(is_null($this->lexer)){
			$this->lexer = $this->createLexer();
		}
		$lexer = $this->lexer;
		
		$lexer->resetTokens();
		
		while($result = $lexer->tokenize($expression)){
			//echo $result."<br />";
		}
		$tokens = $lexer->tokens;
		//print_r($tokens);
		//rimuovo tutte le funzioni
		$tokens = $this->evaluateFunctions($tokens);
		//print($this->printTokens($tokens)."\n");
		//rimuovo tutte le parentesi
		while($this->hasToken($tokens, array("OPEN BRACKET", "CLOSE BRACKET"))){
			$tokens = $this->parseBracket($tokens);
			//print($this->printTokens($tokens)."\n");
		}
		//eseguo la comparazione finale
		//print($this->printTokens($tokens)."\n");
		$tokens = $this->evaluateExpression($tokens);
		//print_r($tokens);
		if(count($tokens) > 1){
			 throw new Exception("Espressione non valida");
		}else{
			return boolval($tokens[0]['token']);
		}
	}
	
	/*
	* Calcola una espressione come stringa
	*/
	public function calculateString($expression){
		if(is_null($this->lexer)){
			$this->lexer = $this->createLexer();
		}
		$lexer = $this->lexer;
		$lexer->resetTokens();
		
		while($result = $lexer->tokenize($expression)){
			//echo $result."<br />";
		}
		$tokens = $lexer->tokens;
		//rimuovo tutte le funzioni
		$tokens = $this->evaluateOperators($tokens);
		if(count($tokens) > 1){
			 throw new Exception("Espressione non valida");
		}else{
			return $tokens[0]['token'];
		}
	}
}
?>