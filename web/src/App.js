import React from 'react';
import ReactDOM from 'react-dom';

import logo from './logo.png';

import 'bootstrap/dist/css/bootstrap.min.css';
import './App.css';

import * as Helpers from './Helpers';

import { Container, Row, Col, Nav, Navbar, NavDropdown, Card, Button, ProgressBar, Form, Alert } from 'react-bootstrap';

import { FaExclamationTriangle, FaCloudDownloadAlt, FaDownload, FaClock, FaPauseCircle, FaTachometerAlt, FaDatabase, FaCheckCircle } from 'react-icons/fa';

const axios = require('axios').default;






class Item extends React.Component {
  constructor(props) {
      super(props);
  }

  render() {

    let data = this.props.data;
    return (
        <Row className="pt-3" key={"item"+data.filename}>
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

                        if(Helpers.empty(data.downloads)) {

                          return (
                            <div>
                              <Card.Text>
                                <FaPauseCircle /> Waiting for Downloader to download.
                              </Card.Text>
                            </div>
                          );

                        } else if(!Helpers.empty(data.downloads)) {

                          let downloads = [];
   
                          Object.keys(data.downloads).forEach(function(key2) {

                            let download = data.downloads[key2];

                            let status = <FaDownload />;

                            if(download.done) {
                              status = <FaCheckCircle />;
                            }

                            if(download) {
                              downloads.push(
                                <Row>
                                  <Col xs="6">{status} {Helpers.basename(download.path)}</Col>
                                  <Col xs="6">
                                    <Row>
                                      <Col xs="3" className="pt-2"><ProgressBar className="bg-dark" variant="danger" now={download.percent} label={`${download.percent}%`} /></Col>
                                      <Col xs="3"><FaDatabase /> {download.sizeText}</Col>
                                      <Col xs="3"><FaTachometerAlt /> {download.speedText}</Col>
                                      <Col xs="3"><FaClock /> {download.timeText}</Col>
                                    </Row>
                                  </Col>
                                </Row>

                              );
                            } else {
                              downloads.push(
                                <Row>
                                  <Col><FaPauseCircle /> Waiting for Download Slot.</Col>
                                </Row>
                              );
                            }

                          });
                    
                          return downloads;

                        } else {
                          return null;
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

  }
}




class List extends React.Component {
  constructor(props) {
      super(props);
  }

  loadList() {

    let _this = this;
    var stateData = this.state;

    axios.get("http://localhost:1337/status").then(function (response) {
        let data = response.data;
        console.log( data );
        _this.setState({data: data});
    })
    .catch(function (error) {
        console.error( error );
        _this.setState({error: error});
    });
  }


  componentDidMount() {
    let _this = this;
    this.loadList();

    setInterval(function(){ 
      _this.loadList(); 
    }, 1000);
  }

  render() {

      if(!Helpers.empty(this.state)) {

          return(
            <div>

              {(() => {

                let _this = this;
                let html = [];

                if(!Helpers.empty(this.state.data)) {
                    Object.keys(this.state.data).forEach(function(key) {
                      html.push(
                          <Item key={"data"+key} data={_this.state.data[key]} />
                      );
                    });
                } else {
                    html.push(
                      <Card className="text bg-warning">
                        <Card.Header>Waiting for Blackhole....</Card.Header>
                      </Card>
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
