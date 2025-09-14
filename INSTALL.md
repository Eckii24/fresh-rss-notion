# Installation Guide

## Prerequisites

1. **FreshRSS** installation with extension support
2. **Notion** account with access to create integrations
3. **YouTube Data API v3** key (optional, but recommended for better metadata)

## Setup Instructions

### 1. Notion Setup

1. Go to [Notion My Integrations](https://www.notion.so/my-integrations)
2. Create a new integration:
   - Name: "FreshRSS Notion Extension" (or any name you prefer)
   - Associated workspace: Select your workspace
   - Type: Internal integration
3. Copy the **Internal Integration Token** (starts with `secret_`)
4. Create or open your Notion database where you want to store videos
5. Add the integration to your database:
   - Click "..." in the top right of your database page
   - Go to "Connections" → "Connect to"
   - Select your integration
6. Copy the **Database ID** from the URL (32-character string after the last slash)

### 2. Database Properties

Your Notion database should have these properties (names are configurable):

- **Url** - URL property type
- **Name** - Title property type  
- **Author** - Multi-select property type
- **Date** - Date property type

Optional additional properties you can configure:
- **Type** - Select/Text (e.g., "Video")
- **Platform** - Select/Text (e.g., "YouTube") 
- **Status** - Select/Status (e.g., "To Read", "Reading", "Done")

### 3. YouTube API Setup (Optional)

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing one
3. Enable the YouTube Data API v3
4. Create credentials (API key)
5. Copy the API key

### 4. FreshRSS Extension Installation

1. Download this extension to your FreshRSS extensions directory:
   ```
   /path/to/freshrss/extensions/xExtension-Notion/
   ```

2. Enable the extension in FreshRSS:
   - Go to Configuration → Extensions
   - Find "Notion" extension and enable it

3. Configure the extension:
   - Click "Configure" next to the Notion extension
   - Fill in your Notion API key and Database ID
   - Optionally add your YouTube API key
   - Configure property mappings to match your database
   - Set any additional fixed properties

## Usage

1. Subscribe to YouTube channels in FreshRSS
2. Open any YouTube video article (not Shorts)
3. Click the "Add to Notion" button at the top of the article
4. The video will be added to your Notion database with metadata
5. The FreshRSS article will be marked as read

## Troubleshooting

### Common Issues

**"Missing Notion API configuration"**
- Ensure you've entered both API key and Database ID
- Verify the integration has access to your database

**"A page with this URL already exists"**
- The video is already in your database
- Check for duplicates before adding

**"Notion API Error: object_not_found"**
- Database ID is incorrect
- Integration doesn't have access to the database

**"YouTube video not detected"**
- Only `/watch` URLs are supported (not `/shorts`)
- Ensure the URL is a valid YouTube video

### Getting Better Metadata

- Configure YouTube API key for more accurate titles and upload dates
- Without API key, extension will scrape HTML (less reliable)

## Configuration Examples

### Basic Setup
```
Notion API Key: secret_abc123...
Database ID: 1234567890abcdef...
URL Property: Url
Name Property: Name  
Author Property: Author
Date Property: Date
```

### With Fixed Properties (JSON format)
```json
{
  "Type": "Video",
  "Platform": "YouTube", 
  "Status": "To Read"
}
```

### With Fixed Properties (Simple format)
```
Type=Video,Platform=YouTube,Status=To Read
```