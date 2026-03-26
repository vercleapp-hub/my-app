# **App Name**: ELDoctor Pay

## Core Features:

- Service Selection: Browse and select from a list of available services with dynamic search and categorization.
- Favorite Services: Ability to mark services as favorites for quick access, saved locally.
- Invoice Generation: Generate a detailed invoice with fields for date, transaction ID, details, amount, and fees.
- Fee Calculation: Automatically calculate fees based on the selected service, with options for fixed fees (internet/landline) and percentage-based fees (mobile recharge).
- Local Storage: Data (such as favorite services, saved invoices, and app preferences) is stored to the local storage to avoid relying on Firestore or a different external database.
- Reporting: Generate reports of saved invoices within a specified date range, searchable by service name or code.
- Export Reports: Export invoice reports to Excel for further analysis and record-keeping.

## Style Guidelines:

- Primary color: Forest green (#4CAF50) to convey trust, growth, and reliability.
- Background color: Light green (#F5FAF5), a desaturated hue from the primary color, to provide a light and clean backdrop.
- Accent color: Olive green (#6B8E23), an analogous hue, for secondary actions, highlights, and visual interest.
- Body and headline font: 'PT Sans', a humanist sans-serif font to offer a balance of modernity and warmth. Use for UI elements in the app such as the names of the services or input labels.
- Code font: 'Source Code Pro' for displaying alphanumeric service codes or transaction reference numbers.
- Use clear and recognizable icons to represent service categories. For example, use a mobile phone icon for mobile services, a water drop icon for water bills, etc.
- Implement a responsive design that adapts to different screen sizes, with a focus on mobile-first design principles to ensure a seamless experience on thermal printers.
- Incorporate subtle animations and transitions to enhance the user experience, such as fading effects on the selected services and loading animations while printing.