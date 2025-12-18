<?php
/**
 * Content Filter Descriptions
 *
 * Comprehensive list of known "the_content" filters with descriptions
 * and email compatibility recommendations for Post Notification plugin.
 *
 * @package PostNotification
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Known "the_content" filters with descriptions and email-focused recommendations.
 *
 * Each entry contains:
 * - 'desc': Short description of what the filter does
 * - 'recommendation': Email compatibility recommendation with emoji indicator
 *   ✅ = Recommended/Safe for email
 *   ⚠️ = Optional/Use with caution
 *   ❌ = Avoid/Not suitable for email
 */
return [
	// ==========================================
	// WordPress Core Filters
	// ==========================================

	'apply_block_hooks_to_content_from_post_object' => [
		'desc' => 'Applies registered block hooks to post content before render.',
		'recommendation' => '⚠️ Optional – rarely needed for plain posts.',
	],

	'do_blocks' => [
		'desc' => 'Renders Gutenberg blocks into HTML.',
		'recommendation' => '✅ Keep – required for block content.',
	],

	'wptexturize' => [
		'desc' => 'Typography tweaks: converts quotes, dashes, ellipses to proper entities.',
		'recommendation' => '✅ Recommended – harmless and improves text layout.',
	],

	'wpautop' => [
		'desc' => 'Wraps plain text into <p> and <br> tags automatically.',
		'recommendation' => '✅ Keep – needed for readable formatting.',
	],

	'shortcode_unautop' => [
		'desc' => 'Removes unwanted <p> tags around shortcodes.',
		'recommendation' => '✅ Usually useful when using shortcodes.',
	],

	'prepend_attachment' => [
		'desc' => 'Prepends attachment content on attachment pages.',
		'recommendation' => '❌ Irrelevant for emails.',
	],

	'wp_filter_content_tags' => [
		'desc' => 'Adds srcset/sizes attributes to media tags for responsive images.',
		'recommendation' => '⚠️ Neutral – often ignored by email clients.',
	],

	'wp_replace_insecure_home_url' => [
		'desc' => 'Replaces insecure http:// home URLs with https://.',
		'recommendation' => '✅ Recommended – ensures secure links.',
	],

	'capital_P_dangit' => [
		'desc' => 'Fixes "Wordpress" → "WordPress" typo.',
		'recommendation' => '✅ Cosmetic, harmless.',
	],

	'convert_smilies' => [
		'desc' => 'Replaces text smilies like :) with emoji images.',
		'recommendation' => '⚠️ Optional – images may break in email.',
	],

	'wp_make_content_images_responsive' => [
		'desc' => 'Adds responsive image attributes (srcset) to content images.',
		'recommendation' => '⚠️ Optional – limited email client support.',
	],

	// ==========================================
	// Embeds & Media
	// ==========================================

	'do_shortcode' => [
		'desc' => 'Executes WordPress shortcodes.',
		'recommendation' => '⚠️ Optional – only if shortcodes render safe HTML.',
	],

	'autoembed' => [
		'desc' => 'Converts media URLs (YouTube, Vimeo, etc.) to embeds (iframes).',
		'recommendation' => '❌ Avoid – iframes not supported in most email clients.',
	],

	'WP_Embed::autoembed' => [
		'desc' => 'Class method for auto-embedding media URLs.',
		'recommendation' => '❌ Avoid – same as autoembed, not email-friendly.',
	],

	'WP_Embed::run_shortcode' => [
		'desc' => 'Processes [embed] shortcode for oEmbed content.',
		'recommendation' => '❌ Avoid – creates iframes unsuitable for email.',
	],

	// ==========================================
	// Lazy Loading & Performance
	// ==========================================

	'wp_lazy_loading_enabled' => [
		'desc' => 'Adds loading="lazy" attribute to images.',
		'recommendation' => '❌ Avoid – not needed/supported in email.',
	],

	'wp_img_tag_add_loading_attr' => [
		'desc' => 'Adds loading attribute to img tags.',
		'recommendation' => '❌ Avoid – irrelevant for email.',
	],

	// ==========================================
	// Security & Sanitization
	// ==========================================

	'wp_kses_post' => [
		'desc' => 'Sanitizes content, removes disallowed HTML tags.',
		'recommendation' => '✅ Recommended – improves security.',
	],

	'sanitize_post' => [
		'desc' => 'Sanitizes post content for database safety.',
		'recommendation' => '✅ Keep – ensures clean content.',
	],

	// ==========================================
	// WooCommerce
	// ==========================================

	'woocommerce_format_content' => [
		'desc' => 'WooCommerce content wrapper with divs.',
		'recommendation' => '❌ Avoid – adds unnecessary layout markup.',
	],

	'wc_format_content' => [
		'desc' => 'Short alias for woocommerce_format_content.',
		'recommendation' => '❌ Avoid – same as above.',
	],

	// ==========================================
	// Yoast SEO
	// ==========================================

	'wpseo_pre_analysis_post_content' => [
		'desc' => 'Yoast SEO content analysis preprocessing.',
		'recommendation' => '❌ Skip – SEO analysis not needed in email.',
	],

	// ==========================================
	// Page Builders (Elementor, Beaver, Divi, etc.)
	// ==========================================

	'elementor/frontend/the_content' => [
		'desc' => 'Elementor page builder content renderer.',
		'recommendation' => '❌ Avoid – complex layout not email-compatible.',
	],

	'fl_builder_render_content' => [
		'desc' => 'Beaver Builder content renderer.',
		'recommendation' => '❌ Avoid – page builder output breaks in email.',
	],

	'et_builder_render_layout' => [
		'desc' => 'Divi Builder layout renderer.',
		'recommendation' => '❌ Avoid – uses complex CSS grid/flexbox.',
	],

	'builder_wrapper' => [
		'desc' => 'Generic page builder container/wrapper.',
		'recommendation' => '❌ Skip – breaks email layout.',
	],

	'parse_content' => [
		'desc' => 'Page builder parser for sections/rows.',
		'recommendation' => '❌ Avoid – not for email.',
	],

	'advanced_hook_content_markup' => [
		'desc' => 'Adds theme or builder markup wrapper.',
		'recommendation' => '❌ Avoid – adds unnecessary div structure.',
	],

	// ==========================================
	// Gallery & Image Plugins
	// ==========================================

	'NextGEN_shortcodes::convert_shortcode' => [
		'desc' => 'Renders NextGEN Gallery shortcodes.',
		'recommendation' => '⚠️ Optional – may produce unsupported HTML.',
	],

	'envira_gallery_output' => [
		'desc' => 'Envira Gallery lightbox output.',
		'recommendation' => '❌ Avoid – JavaScript gallery not email-compatible.',
	],

	'_render_related_images' => [
		'desc' => 'Adds related images section to content.',
		'recommendation' => '❌ Avoid – often uses CSS grid and JS.',
	],

	// ==========================================
	// Jetpack
	// ==========================================

	'jetpack_photon_url' => [
		'desc' => 'Jetpack Photon CDN image optimization.',
		'recommendation' => '✅ Keep – optimizes images, email-safe.',
	],

	'jetpack_lazy_images' => [
		'desc' => 'Jetpack lazy loading for images.',
		'recommendation' => '❌ Avoid – lazy loading not needed in email.',
	],

	// ==========================================
	// Social Sharing & Related Content
	// ==========================================

	'sharing_display' => [
		'desc' => 'Adds social sharing buttons (Jetpack/other).',
		'recommendation' => '❌ Avoid – JavaScript buttons don\'t work in email.',
	],

	'add_related_posts' => [
		'desc' => 'Appends related posts section.',
		'recommendation' => '⚠️ Optional – may work if simple HTML links.',
	],

	// ==========================================
	// Legacy Themes & Uncommon Filters
	// ==========================================

	'check_weaverii' => [
		'desc' => 'Weaver II theme tweak – legacy.',
		'recommendation' => '❌ Remove – obsolete theme filter.',
	],

	'fix_nested_shortcodes' => [
		'desc' => 'Fixes badly nested shortcodes in content.',
		'recommendation' => '⚠️ Optional – may help with malformed content.',
	],

	'flush_header_buffer' => [
		'desc' => 'Flushes output buffer before printing content.',
		'recommendation' => '❌ Useless for email rendering – skip.',
	],

	'show_info' => [
		'desc' => ' Debug/info output (theme-specific).',
		'recommendation' => '❌ Skip – not relevant for mail.',
	],

	'run_shortcode' => [
		'desc' => 'Alias of do_shortcode (plugin-specific).',
		'recommendation' => '⚠️ Same as do_shortcode – optional.',
	],

	// ==========================================
	// Accessibility
	// ==========================================

	'wp_targeted_link_rel' => [
		'desc' => 'Adds rel="noopener" to external links for security.',
		'recommendation' => '✅ Recommended – improves link security.',
	],

	// ==========================================
	// Caching & Optimization Plugins
	// ==========================================

	'rocket_lazyload_html' => [
		'desc' => 'WP Rocket lazy loading implementation.',
		'recommendation' => '❌ Avoid – not needed in email.',
	],

	'autoptimize_filter_html_keepcomments' => [
		'desc' => 'Autoptimize HTML optimization filter.',
		'recommendation' => '❌ Skip – optimization not needed for email.',
	],

	// ==========================================
	// Multilingual Plugins
	// ==========================================

	'wpml_content_fix_links' => [
		'desc' => 'WPML fixes links for translated content.',
		'recommendation' => '✅ Keep – ensures correct language links.',
	],

	'pll_the_content' => [
		'desc' => 'Polylang translation filter.',
		'recommendation' => '✅ Keep – needed for correct language output.',
	],

	// ==========================================
	// Custom Content Types
	// ==========================================

	'acf_the_content' => [
		'desc' => 'Advanced Custom Fields content processing.',
		'recommendation' => '⚠️ Optional – depends on field types used.',
	],
];