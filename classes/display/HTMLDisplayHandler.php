<?php

class HTMLDisplayHandler {
	/**
	 * @brief Produce HTML compliant content given a module object.\n
	 * @param[in] $oModule the module object
	 **/
	function toDoc(&$oModule) 
	{
		$oTemplate = &TemplateHandler::getInstance();

		// compile module tpl
		$template_path = $oModule->getTemplatePath();
		$tpl_file = $oModule->getTemplateFile();
		$output = $oTemplate->compile($template_path, $tpl_file);

		// add #xeAdmin div for adminitration pages
		if(Context::getResponseMethod() == 'HTML') {
			if(Context::get('module')!='admin' && strpos(Context::get('act'),'Admin')>0) $output = '<div id="xeAdmin">'.$output.'</div>';

			if(Context::get('layout') != 'none') {
				if(__DEBUG__==3) $start = getMicroTime();

				Context::set('content', $output, false);

				$layout_path = $oModule->getLayoutPath();
				$layout_file = $oModule->getLayoutFile();

				$edited_layout_file = $oModule->getEditedLayoutFile();

				// get the layout information currently requested
				$oLayoutModel = &getModel('layout');
				$layout_info = Context::get('layout_info');
				$layout_srl = $layout_info->layout_srl;

				// compile if connected to the layout
				if($layout_srl > 0){

					// handle separately if the layout is faceoff
					if($layout_info && $layout_info->type == 'faceoff') {
						$oLayoutModel->doActivateFaceOff($layout_info);
						Context::set('layout_info', $layout_info);
					}

					// search if the changes CSS exists in the admin layout edit window
					$edited_layout_css = $oLayoutModel->getUserLayoutCss($layout_srl);

					if(file_exists($edited_layout_css)) Context::addCSSFile($edited_layout_css,true,'all','',100);
				}
				if(!$layout_path) $layout_path = "./common/tpl";
				if(!$layout_file) $layout_file = "default_layout";
				$output = $oTemplate->compile($layout_path, $layout_file, $edited_layout_file);

				if(__DEBUG__==3) $GLOBALS['__layout_compile_elapsed__'] = getMicroTime()-$start;

				if(preg_match('/MSIE/i',$_SERVER['HTTP_USER_AGENT']) && (Context::get("_use_ssl")=='optional'||Context::get("_use_ssl")=="always")) {
					Context::addHtmlFooter('<iframe id="xeTmpIframe" name="xeTmpIframe" style="width:1px;height:1px;position:absolute;top:-2px;left:-2px;"></iframe>');
				}
			}
		}
		return $output;
	}

	function prepareToPrint(&$output) {
		if(Context::getResponseMethod() != 'HTML') return;
		
		if(__DEBUG__==3) $start = getMicroTime();

		// move <style ..></style> in body to the header
		$output = preg_replace_callback('!<style(.*?)<\/style>!is', array($this,'_moveStyleToHeader'), $output);

		// change a meta fine(widget often put the tag like <!--Meta:path--> to the content because of caching)
		$output = preg_replace_callback('/<!--(#)?Meta:([a-z0-9\_\/\.\@]+)-->/is', array($this,'_transMeta'), $output);

		// handles a relative path generated by using the rewrite module
		if(Context::isAllowRewrite()) {
			$url = parse_url(Context::getRequestUri());
			$real_path = $url['path'];

			$pattern = '/src=("|\'){1}(\.\/)?(files\/attach|files\/cache|files\/faceOff|files\/member_extra_info|modules|common|widgets|widgetstyle|layouts|addons)\/([^"\']+)\.(jpg|jpeg|png|gif)("|\'){1}/s';
			$output = preg_replace($pattern, 'src=$1'.$real_path.'$3/$4.$5$6', $output);

			$pattern = '/href=("|\'){1}(\?[^"\']+)/s';
			$output = preg_replace($pattern, 'href=$1'.$real_path.'$2', $output);

			if(Context::get('vid')) {
				$pattern = '/\/'.Context::get('vid').'\?([^=]+)=/is';
				$output = preg_replace($pattern, '/?$1=', $output);
			}
		}

		// prevent the 2nd request due to url(none) of the background-image
		$output = preg_replace('/url\((["\']?)none(["\']?)\)/is', 'none', $output);

		if(__DEBUG__==3) $GLOBALS['__trans_content_elapsed__'] = getMicroTime()-$start;

		// Remove unnecessary information
		$output = preg_replace('/member\_\-([0-9]+)/s','member_0',$output);

		// convert the final layout
		Context::set('content', $output);
		$oTemplate = &TemplateHandler::getInstance();
		if(Mobile::isFromMobilePhone()) {
			$output = $oTemplate->compile('./common/tpl', 'mobile_layout');
		}
		else
		{
			$this->_loadJSCSS();
			$output = $oTemplate->compile('./common/tpl', 'common_layout');
		}

		// replace the user-defined-language
		$oModuleController = &getController('module');
		$oModuleController->replaceDefinedLangCode($output);
	}

	/**
	 * @brief add html style code extracted from html body to Context, which will be
	 * printed inside <header></header> later.
	 * @param[in] $oModule the module object
	 **/
	function _moveStyleToHeader($matches) {
		Context::addHtmlHeader($matches[0]);
	}

	/**
	 * @brief add given .css or .js file names in widget code to Context       
	 * @param[in] $oModule the module object
	 **/
	function _transMeta($matches) {
		if($matches[1]) return '';
		if(substr($matches[2],'-4')=='.css') Context::addCSSFile($matches[2]);
		elseif(substr($matches[2],'-3')=='.js') Context::addJSFile($matches[2]);
	}

	function _loadJSCSS()
	{
		$oContext =& Context::getInstance();
		// add common JS/CSS files
		if(__DEBUG__) {
			$oContext->addJsFile('./common/js/jquery.js', false, '', -100000);
			$oContext->addJsFile('./common/js/x.js', false, '', -100000);
			$oContext->addJsFile('./common/js/common.js', false, '', -100000);
			$oContext->addJsFile('./common/js/js_app.js', false, '', -100000);
			$oContext->addJsFile('./common/js/xml_handler.js', false, '', -100000);
			$oContext->addJsFile('./common/js/xml_js_filter.js', false, '', -100000);
			$oContext->addCSSFile('./common/css/default.css', false, 'all', '', -100000);
			$oContext->addCSSFile('./common/css/button.css', false, 'all', '', -100000);
		} else {
			$oContext->addJsFile('./common/js/jquery.min.js', false, '', -100000);
			$oContext->addJsFile('./common/js/x.min.js', false, '', -100000);
			$oContext->addJsFile('./common/js/xe.min.js', false, '', -100000);
			$oContext->addCSSFile('./common/css/xe.min.css', false, 'all', '', -100000);
		}

		// for admin page, add admin css
		if(Context::get('module')=='admin' || strpos(Context::get('act'),'Admin')>0){
			if(__DEBUG__) {
				$oContext->addCSSFile('./modules/admin/tpl/css/admin.css', false, 'all', '', 100000);
			} else {
				$oContext->addCSSFile('./modules/admin/tpl/css/admin.min.css', false, 'all', '',10000);
			}
		}
	}
}
