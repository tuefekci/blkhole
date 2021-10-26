import React from 'react';
import ReactDOM from 'react-dom';

import logo from './logo.png';

import 'bootstrap/dist/css/bootstrap.min.css';
import './App.css';

import * as Helpers from './Helpers';

import { Container, Row, Col, Nav, Navbar, NavDropdown, Card, Button, ProgressBar, Form } from 'react-bootstrap';

import { FaExclamationTriangle, FaCloudDownloadAlt, FaDownload } from 'react-icons/fa';

const axios = require('axios').default;

class List extends React.Component {
  constructor(props) {
      super(props);
  }

  loadList() {

    var _this = this;
    var stateData = this.state;

    axios.get("http://localhost:1337/status").then(function (response) {
        var data = response.data;
        _this.setState({data: data});
    })
    .catch(function (error) {
        console.error( error );
        _this.setState({error: error});
    });
  }


  componentDidMount() {
    var _this = this;
    this.loadList();

    setInterval(function(){ 
      _this.loadList(); 
    }, 5000);
  }

  render() {

      if(!Helpers.empty(this.state)) {

          return(
            <div>

              {(() => {

                var _this = this;
                var html = [];

                if(!Helpers.empty(this.state.data)) {
                    Object.keys(this.state.data).forEach(function(key) {

                        var data = _this.state.data[key];
                        html.push(
                            <Row className="pt-3" key={'listItem'+key}>
                              <Col>
                                <Card className="text bg-warning">
                                  <Card.Header>{data.filename}</Card.Header>
                                  <Card.Body>

                                    {(() => {

                                      if(Helpers.empty(data.provider)) {

                                        return (
                                          <Card.Text>
                                            <FaExclamationTriangle /> Waiting for transfer to Provider.
                                          </Card.Text>
                                        );

                                      } else {

                                        if(!Helpers.empty(data.provider) && !data.provider.ready) {

                                          return (
                                            <div>
                                              <Card.Text>
                                                <FaCloudDownloadAlt /> Waiting for Provider Download to finish.
                                              </Card.Text>
                                            </div>
                                          );

                                        } else if(!Helpers.empty(data.provider) && data.provider.ready) {

                                            if(Helpers.empty(data.downloads) && data.provider.ready) {

                                              return (
                                                <div>
                                                  <Card.Text>
                                                    <FaDownload /> Waiting for Downloader to download.
                                                  </Card.Text>
                                                </div>
                                              );

                                            } else if(!Helpers.empty(data.downloads) && data.provider.ready) {

                                              var downloads = [];
                                              Object.keys(data.downloads).forEach(function(key) {

                                                var download = data.downloads[key];

                                                if(download) {
                                                  downloads.push(
                                                    <ProgressBar className="bg-dark" variant="danger" now={download.percent} label={`${download.percent}%`} />
                                                  );
                                                } else {
                                                  downloads.push(
                                                    <Card.Text>
                                                      <FaDownload /> Waiting for Download Slot.
                                                    </Card.Text>
                                                  );
                                                }

                                              });

                                              return html;

                                            }


                                        }



                                      }

                                      return null;

                                    })(this)}





                                  </Card.Body>
                                </Card>
                              </Col>
                            </Row>
                          );

                    });
                } else {
                    html.push(
                        <div>
                            <stong>Waiting for Blackhole.</stong>
                        </div>
                    );
                }

                return html;

              })(this)}

            </div>
          );

      } else {
          return null;
      }

  }
}

function App() {
  return (
    <Container fluid>

      <Navbar collapseOnSelect expand="lg" bg="warning" variant="light">
        <Container>
        <Navbar.Brand href="#home"><img src={logo} className="img-responsive logo" alt="logo" /></Navbar.Brand>
        <Navbar.Toggle aria-controls="responsive-navbar-nav" />
        <Navbar.Collapse id="responsive-navbar-nav">
          <Nav className="mr-auto">

          </Nav>
          <Nav>
            <Nav.Link href="#settings">Settings</Nav.Link>
          </Nav>
        </Navbar.Collapse>
        </Container>
      </Navbar>

      <Container className="main-content">

        <Row className="pb-3">
          <Col>
            <Card className="text bg-warning">
              <Card.Header>Add Magnet</Card.Header>
              <Card.Body className="pb-0">
                <Form>
                  <Form.Group className="" controlId="formMagnet">
                    <Form.Control type="magnet" placeholder="magnet:xt=urn:btih:xxx" />
                  </Form.Group>
                </Form>
              </Card.Body>
            </Card>
          </Col>
          <Col>
            <Card className="text bg-warning">
                <Card.Header>Add Torrent</Card.Header>
                <Card.Body className="pb-0">
                  <Form>
                    <Form.Group controlId="formTorrent">
                      <Button variant="danger" className="btn-block">Enter Torrent</Button>
                    </Form.Group>
                  </Form>
                </Card.Body>
              </Card>
          </Col>
        </Row>

        <List />

      </Container>


    </Container>
  );
}

export default App;
