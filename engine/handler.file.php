<?php/* Wave FrameworkIndex gateway file handlerFile handler is used for every file that is accessed and is not already served by other handlers. This handler serves files such as PDF files or other files that are not usually considered 'web' formats. It also allows to return a file within byte range (which is useful for streams).* Proper headers and file format detection* Can return part of the file using bytes rangeAuthor and support: Kristo Vaher - kristo@waher.netLicense: GNU Lesser General Public License Version 3*/// INITIALIZATION	// Stopping all requests that did not come from Index gateway	if(!isset($resourceAddress)){		header('HTTP/1.1 403 Forbidden');		die();	}	// If filename includes & symbol, then system assumes it should be dynamically generated	$parameters=array_unique(explode('&',$resourceFile));	// Getting the downloadable file name	$resourceFile=array_pop($parameters);	// The amount of non-filenames in the request	$parameterCount=count($parameters);		// Range of bytes to return	// This allows user agent to request only part of the file	if(isset($_SERVER['HTTP_RANGE'])){		$tmp=explode('=',$_SERVER['HTTP_RANGE']);		$bytesData=explode('-',array_pop($tmp));		if(isset($bytesData) && is_numeric($bytesData[0]) && is_numeric($bytesData[1])){			$bytesFrom=$bytesData[0];			$bytesTo=$bytesData[1];		}	}	// No cache flag	if(in_array('nocache',$parameters)){		$noCache=true;	} else {		$noCache=false;	}	// Default cache timeout of one month, unless timeout is set	if(!isset($config['resource-cache-timeout'])){		$config['resource-cache-timeout']=31536000; // A year	}// CHECK FOR PARAMETER SUPPORT	// If more than one parameter is set, it returns 404	// 404 is also returned if file does not actually exist	if($parameterCount>1 || ($parameterCount==1 && !$noCache) || !file_exists($resourceFolder.$resourceFile)){		// Adding log entry			if(isset($logger)){			// Assigning custom log data to logger			$logger->setCustomLogData(array('category'=>'image','response-code'=>'404'));			// Writing log entry			$logger->writeLog();		}		// Returning 404 header		header('HTTP/1.1 404 Not Found');		die();			}	// Last-modified date	$lastModified=filemtime($resourceFolder.$resourceFile);// NOT MODIFIED CHECK	// Checking if file has been modified or not	if(!$noCache){		// If the request timestamp is exactly the same, then we let the browser know of this		if((isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'])>=$lastModified) || (isset($_SERVER['HTTP_IF_RANGE']) && strtotime($_SERVER['HTTP_IF_RANGE'])>=$lastModified)){			// Adding log entry				if(isset($logger)){				// Assigning custom log data to logger				$logger->setCustomLogData(array('cache-used'=>true,'category'=>'image','response-code'=>'304'));				// Writing log entry				$logger->writeLog();			}			// Cache headers (Last modified is never sent with 304 header)			header('Cache-Control: public,max-age='.$config['resource-cache-timeout']);			header('Expires: '.gmdate('D, d M Y H:i:s',($_SERVER['REQUEST_TIME']+$config['resource-cache-timeout'])).' GMT');			// Returning 304 header			header('HTTP/1.1 304 Not Modified');			die();		}	}// DETECTING MIME TYPE	// Currently assumed MIME type	$mimeType='';	// Finding the proper MIME type	if(extension_loaded('fileinfo')){		// This opens MIME type 'magic' resource for use		if($fileInfo=finfo_open(FILEINFO_MIME_TYPE)){			// Finding MIME type with magic resource			$mimeType=finfo_file($fileInfo,$resourceFolder.$resourceFile);			// Resourse is not needed further, so it is closed			finfo_close($fileInfo);		}			} else {		// Since Fileinfo was not available, we use extension-based detection as fallback		switch($resourceExtension){			case 'ico':				$mimeType='image/vnd.microsoft.icon;';				break;			case 'zip':				$mimeType='application/zip';				break;			case 'mp3':				$mimeType='audio/mpeg';				break;			case 'gif':				$mimeType='image/gif';				break;			case 'tif':				$mimeType='image/tiff';				break;		}				}	// HEADERS	// Assigning MIME type if it was found	if($mimeType && $mimeType!=''){		// Detected mime type is set as content-type header		header('Content-Type: '.$mimeType.';');	} else {		// Octet stream is a general-use unknown resource, and browsers will often attempt to 'download' such a file		header('Content-Type: application/octet-stream;');		header('Content-Disposition: attachment; filename='.$resourceFile);	}			// If cache is used, then proper headers will be sent	if($noCache){		// User agent is told to cache these results for set duration		header('Cache-Control: public,max-age=0');		header('Expires: '.gmdate('D, d M Y H:i:s',$_SERVER['REQUEST_TIME']).' GMT');		header('Last-Modified: '.$lastModified.' GMT');	} else {		// User agent is told to cache these results for set duration		header('Cache-Control: public,max-age='.$config['resource-cache-timeout']);		header('Expires: '.gmdate('D, d M Y H:i:s',($_SERVER['REQUEST_TIME']+$config['resource-cache-timeout'])).' GMT');		header('Last-Modified: '.gmdate('D, d M Y H:i:s',$lastModified).' GMT');	}	// Robots header	if(isset($config['file-robots'])){		// If file-specific robots setting is defined		header('X-Robots-Tag: '.$config['file-robots'],true);	} elseif(isset($config['robots'])){		// This sets general robots setting, if it is defined in configuration file		header('X-Robots-Tag: '.$config['robots'],true);	} else {		// If robots setting is not configured, system tells user agent not to cache the file		header('X-Robots-Tag: noindex,nocache,nofollow,noarchive,noimageindex,nosnippet',true);	}	// Pragma header removed should the server happen to set it automatically	header_remove('Pragma');	// OUTPUT	// If user agent only requested part of the file to be returned	if(isset($bytesFrom,$bytesTo)){		// Getting current output length		$contentLength=filesize($resourceFolder.$resourceFile);		if($bytesTo<=$contentLength){			// Required for range response			header('HTTP/1.1 206 Partial Content');			header('Content-Range: bytes '.$bytesFrom.'-'.$bytesTo.'/'.$contentLength);			// Content length is defined that can speed up website requests, letting user agent to determine file size			header('Content-Length: '.($bytesTo-$bytesFrom));			// Returning part of the file			$fileHandle=fopen($resourceFolder.$resourceFile,'r');			fseek($fileHandle,$bytesFrom);			// Returning the data to user agent			echo fread($fileHandle,($bytesTo-$bytesFrom));		} else {			header('HTTP/1.1 416 Requested Range Not Satisfiable');		}	} else {		// Getting current output length		$contentLength=filesize($resourceFolder.$resourceFile);		// Content length is defined that can speed up website requests, letting user agent to determine file size		header('Content-Length: '.$contentLength);		// Returning the file to user agent		readfile($resourceFolder.$resourceFile);	}	// WRITING TO LOG	// If Logger is defined then request is logged and can be used for performance review later	if(isset($logger)){		// Assigning custom log data to logger		$logger->setCustomLogData(array('cache-used'=>false,'category'=>'file','content-length-used'=>$contentLength));		// Writing log entry		$logger->writeLog();	}?>