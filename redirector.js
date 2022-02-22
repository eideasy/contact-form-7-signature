document.addEventListener('wpcf7mailsent', function (event) {
  console.log('mail sent data', event)
  let postedDataHash = event.detail.apiResponse.posted_data_hash;
  let params = '?action=eideasy_signing_url&posted_data_hash=' + postedDataHash
  fetch(eideasy_settings.ajaxUrl + params).then(response => response.json()).then(responseJson => {
    console.log(responseJson)
    if (responseJson.signing_url) {
      window.location = responseJson.signing_url
    } else {
      console.log('Not redirecting as signing url not found')
    }
  }, false)
})
