import defaultTheme from 'tailwindcss/defaultTheme'
import forms from '@tailwindcss/forms'
import typography from '@tailwindcss/typography'

/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    './storage/framework/views/*.php',
    './resources/views/**/*.blade.php',
    './resources/**/*.js',
    './resources/**/*.vue',
  ],

  theme: {
    extend: {
      fontFamily: {
        sans: ['Figtree', ...defaultTheme.fontFamily.sans],
      },
      typography: ({ theme }) => ({
        DEFAULT: {
          css: {
            color: theme('colors.gray.800'),
            maxWidth: 'none',
            a: {
              color: theme('colors.indigo.600'),
              textDecoration: 'none',
              fontWeight: '500',
              '&:hover': {
                color: theme('colors.orange.600'),
                textDecoration: 'underline',
              },
            },
            h1: {
              color: theme('colors.gray.900'),
              fontWeight: '700',
              fontSize: theme('fontSize.2xl')[0],
            },
            h2: {
              color: theme('colors.gray.900'),
              fontWeight: '600',
              fontSize: theme('fontSize.xl')[0],
            },
            h3: {
              color: theme('colors.gray.900'),
              fontWeight: '600',
              fontSize: theme('fontSize.lg')[0],
            },
            strong: { color: theme('colors.gray.900') },
            blockquote: {
              fontStyle: 'italic',
              borderLeftColor: theme('colors.orange.500'),
              color: theme('colors.gray.700'),
            },
            ul: {
              listStyleType: 'disc',
            },
            ol: {
              listStyleType: 'decimal',
            },
            code: {
              backgroundColor: theme('colors.gray.100'),
              color: theme('colors.indigo.700'),
              padding: '2px 5px',
              borderRadius: '4px',
            },
          },
        },
      }),
    },
  },

  plugins: [forms, typography],
}
