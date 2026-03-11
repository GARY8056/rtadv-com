export const BLOG_RULES = {
  version: 'blog-full-scan-v2',
  scope: {
    postType: 'post',
  },
  post: {
    minInlineImages: 3,
    requireFeaturedMedia: true,
    requireH2: true,
    disallowChineseSlug: false,
  },
  media: {
    maxBytes: 70 * 1024,
    requiredFields: ['alt_text', 'title', 'caption', 'description'],
  },
  links: {
    legacyContactPaths: ['/contact', '/contact/', '/contact-us', '/contact-us/'],
    blockedDomains: ['boxon.com'],
  },
};

export const APPLY_ALLOWED_PHASES = new Set([
  'seo-fix',
  'inline-image-gap-fix',
  'media-meta-fix',
  'structure-fix',
  'link-fix',
  'slug-apply',
  'media-file-fix',
  'featured-media-migration',
]);

export const IMPLEMENTED_PHASES = new Set([
  'scan',
  'scan-posts',
  'scan-media',
  'orphaned-media-analysis',
  'inline-image-gap-analysis',
  'inline-image-gap-plan',
  'inline-image-gap-fix',
  'seo-audit',
  'seo-fix',
  'post-audit',
  'structure-fix',
  'media-audit',
  'media-meta-fix',
  'media-file-fix',
  'featured-media-migration',
  'verify',
]);
