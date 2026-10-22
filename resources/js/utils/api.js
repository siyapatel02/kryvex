export async function getJson(url) {
    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
        },
    });

    const data = await response.json();

    if (!response.ok || data.success === false) {
        throw new Error(data.message || 'Something went wrong.');
    }

    return data;
}